<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Bill;
use App\Services\ExternalBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class ReconcileBills extends Command
{
    protected $signature = 'bills:reconcile
                            {--years=2 : How many years back to fetch from ERP}
                            {--chunk=20 : Number of customers to process in parallel}';

    protected $description = 'Sync ALL historical ERP bills per customer, then cross-reference against unpaid dues to correctly mark settled invoices as paid.';

    public function handle(ExternalBillingService $billingService)
    {
        $years   = (int) $this->option('years');
        $chunk   = (int) $this->option('chunk');
        $fromDate = now()->subYears($years)->format('Y-m-d');
        $toDate   = now()->format('Y-m-d');
        $baseUrl  = rtrim(config('services.external_billing.url'), '/');

        if (empty($baseUrl)) {
            $this->error('EXTERNAL_BILLING_URL is not set in .env');
            return 1;
        }

        $this->info("Step 1: Fetching all bills from ERP for all customers ({$fromDate} → {$toDate})...");

        // --- Step 1: Load unpaid invoice numbers into memory for O(1) lookup ---
        $unpaidInvoiceNos = DB::table('bills')
            ->where('is_settled', false)
            ->whereNotNull('invoice_no')
            ->pluck('invoice_no')
            ->flip() // indexed by invoice_no for O(1) lookup
            ->all();

        $this->info('Loaded ' . count($unpaidInvoiceNos) . ' known unpaid invoices into memory.');

        // --- Step 2: Sync all historical bills from ERP per customer ---
        $customers  = Customer::whereNotNull('external_cucode')->get();
        $totalCount = $customers->count();
        $this->info("Processing {$totalCount} customers in parallel chunks of {$chunk}...");

        $totalSynced     = 0;
        $totalReconciled = 0;
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        foreach ($customers->chunk($chunk) as $batch) {
            // Parallel HTTP requests for this batch
            $responses = Http::pool(function (Pool $pool) use ($batch, $baseUrl, $fromDate, $toDate) {
                return $batch->map(function ($customer) use ($pool, $baseUrl, $fromDate, $toDate) {
                    return $pool
                        ->as((string) $customer->id)
                        ->timeout(30)
                        ->withOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4])
                        ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                        ->asMultipart()
                        ->post("{$baseUrl}/API/announcements/bill_master.php", [
                            ['name' => 'cucode',    'contents' => $customer->external_cucode],
                            ['name' => 'from_date', 'contents' => $fromDate],
                            ['name' => 'to_date',   'contents' => $toDate],
                        ]);
                });
            });

            $upsertRows = [];

            foreach ($responses as $customerId => $response) {
                if (!($response instanceof \Illuminate\Http\Client\Response) || !$response->successful()) {
                    $bar->advance();
                    continue;
                }

                $data = $response->json();
                $bills = $data['data'] ?? [];

                if (empty($bills)) {
                    $bar->advance();
                    continue;
                }

                foreach ($bills as $bill) {
                    $invoiceNo = $bill['BILLNO'] ?? $bill['BN'] ?? null;
                    if (!$invoiceNo) continue;

                    $netAmount  = (float) ($bill['NETAMOUNT'] ?? 0);
                    $billDate   = isset($bill['DATE']) ? Carbon::parse($bill['DATE']) : now();
                    $lockDays   = 15; // Standard credit period
                    $dueDate    = (clone $billDate)->addDays($lockDays);

                    // --- The Reconciliation Logic ---
                    // If the invoice number is NOT in our unpaid list, it is settled.
                    $isSettled = !isset($unpaidInvoiceNos[$invoiceNo]);

                    // payment_status ENUM only allows: unpaid, payment_submitted, proof_rejected, paid
                    $paymentStatus = $isSettled ? 'paid' : 'unpaid';

                    // status column tracks aging more granularly
                    $status = $isSettled ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'unpaid');

                    $upsertRows[] = [
                        'customer_id'    => $customerId,
                        'invoice_no'     => $invoiceNo,
                        'bill_date'      => $billDate->format('Y-m-d'),
                        'due_date'       => $dueDate->format('Y-m-d'),
                        'subtotal'       => $netAmount,
                        'gst_total'      => 0,
                        'grand_total'    => $netAmount,
                        'is_settled'     => $isSettled,
                        'status'         => $status,
                        'payment_status' => $paymentStatus,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];

                    if ($isSettled) $totalReconciled++;
                }

                $bar->advance();
            }

            // Upsert in bulk. Only update the settlement/status fields — never touch payment_method, utr_number, proof etc.
            if (!empty($upsertRows)) {
                foreach (array_chunk($upsertRows, 500) as $chunk500) {
                    DB::table('bills')->upsert(
                        $chunk500,
                        ['invoice_no'],
                        ['bill_date', 'due_date', 'grand_total', 'subtotal', 'is_settled', 'status', 'payment_status', 'updated_at']
                    );
                }
                $totalSynced += count($upsertRows);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Sync complete. Total bills processed: {$totalSynced}");
        $this->info("✓ Bills automatically reconciled as paid: {$totalReconciled}");
        $this->info("✓ Remaining unsettled (active dues): " . (count($unpaidInvoiceNos)));
    }
}
