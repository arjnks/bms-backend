<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExternalBillingService;
use App\Models\Customer;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncUnpaidBills extends Command
{
    protected $signature = 'sync:unpaid-bills';
    protected $description = 'Fetch unpaid historical bills and update aging balances (memory-safe streaming version)';

    // How many records to upsert per DB batch
    private const UPSERT_CHUNK = 500;

    public function handle(ExternalBillingService $billingService)
    {
        $this->info("Fetching unpaid bills from external API (streaming, page-by-page)...");

        // Use a sync-run timestamp to identify which bills were touched in THIS run.
        // Any unsettled bill that was NOT updated during this run must have been
        // dropped from the ERP's unpaid ledger — meaning it is now fully paid.
        $syncStartedAt = now();

        // Pre-load customer lookup table (small dataset, fine to keep in memory)
        $customers = Customer::whereNotNull('external_cucode')
            ->pluck('id', 'external_cucode'); // ['cucode' => customer_id]

        $totalProcessed = 0;
        $totalSkipped   = 0;
        $pageNum        = 0;

        foreach ($billingService->streamUnpaidBills() as $batch) {
            $pageNum++;
            $this->info("Processing page {$pageNum} (" . count($batch) . " records)...");

            $upsertData = [];

            foreach ($batch as $data) {
                $cucode    = $data['cucode'] ?? null;
                $invoiceNo = (string)($data['billno'] ?? '');

                if (!$cucode || !$invoiceNo || !isset($customers[$cucode])) {
                    $totalSkipped++;
                    continue;
                }

                $customerId  = $customers[$cucode];
                $netAmount   = (float)($data['netamount'] ?? 0);
                // Handle the ERP's non-standard 'amountrecieved' spelling (+ fallbacks)
                $amtReceived = (float)($data['amountrecieved'] ?? $data['amtreceived'] ?? $data['amount_received'] ?? 0);
                $isSettled   = ($data['settled'] ?? 'N') === 'Y';
                $agingDays   = (int)($data['ddays'] ?? 0);
                $lockDays    = (int)($data['lockdays'] ?? 0);

                $billDate = isset($data['date']) ? Carbon::parse($data['date']) : now();
                $dueDate  = (clone $billDate)->addDays($lockDays);

                $upsertData[] = [
                    'customer_id'     => $customerId,
                    'invoice_no'      => $invoiceNo,
                    'bill_date'       => $billDate->format('Y-m-d'),
                    'due_date'        => $dueDate->format('Y-m-d'),
                    'subtotal'        => $netAmount,
                    'gst_total'       => 0,
                    'grand_total'     => $netAmount,
                    'amount_received' => $amtReceived,
                    'is_settled'      => $isSettled,
                    'aging_days'      => $agingDays,
                    'lock_days'       => $lockDays,
                    'status'          => $isSettled ? 'paid' : ($agingDays > $lockDays ? 'overdue' : 'unpaid'),
                    'payment_status'  => $isSettled ? 'paid' : 'unpaid',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                // Flush every UPSERT_CHUNK records to keep memory flat
                if (count($upsertData) >= self::UPSERT_CHUNK) {
                    $this->flushUpsert($upsertData);
                    $totalProcessed += count($upsertData);
                    $upsertData = [];
                }
            }

            // Flush any remainder from this page
            if (!empty($upsertData)) {
                $this->flushUpsert($upsertData);
                $totalProcessed += count($upsertData);
                $upsertData = [];
            }

            // Free the batch from memory explicitly
            unset($batch);
            gc_collect_cycles();
        }

        $this->info("Processed {$totalProcessed} bills. Skipped {$totalSkipped} (unknown customer / missing invoice).");

        // Ghost bill cleanup removed. The ERP API truncates at 50,000 records,
        // so we cannot assume that bills missing from the API are fully paid.

        $this->info("Sync complete.");
    }

    /**
     * Upsert a chunk of bill records into the database.
     * Columns in the update list are the only ones that change on conflict.
     * customer_id + invoice_no is the unique composite key.
     */
    private function flushUpsert(array $data): void
    {
        DB::table('bills')->upsert(
            $data,
            ['customer_id', 'invoice_no'],  // composite unique key — invoice_no alone is NOT globally unique
            [                    // columns to update on duplicate
                'bill_date', 'due_date', 'subtotal', 'grand_total',
                'amount_received', 'is_settled', 'aging_days', 'lock_days',
                'status', 'payment_status', 'updated_at',
            ]
        );
    }
}
