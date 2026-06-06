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
        $this->info('Fetching unpaid bills per customer from ERP...');

        $customers = Customer::whereNotNull('external_cucode')
            ->pluck('id', 'external_cucode'); // ['cucode' => customer_id]

        $totalProcessed = 0;
        $totalSkipped   = 0;

        foreach ($customers as $cucode => $customerId) {
            try {
                $bills = $billingService->getBills((string) $cucode);
            } catch (\Exception $e) {
                $this->warn("Failed to fetch bills for cucode {$cucode}: " . $e->getMessage());
                continue;
            }

            if (empty($bills)) {
                continue;
            }

            $upsertData = [];
            $now = now()->format('Y-m-d H:i:s');

            foreach ($bills as $data) {
                $invoiceNo  = (string)($data['billno'] ?? $data['BN'] ?? $data['BILLNO'] ?? '');
                if (!$invoiceNo) {
                    $totalSkipped++;
                    continue;
                }

                $netAmount   = (float)($data['netamount'] ?? $data['NETAMOUNT'] ?? 0);
                $amtReceived = (float)($data['amountrecieved'] ?? $data['amtreceived'] ?? $data['amount_received'] ?? 0);
                $isSettled   = ($data['settled'] ?? 'N') === 'Y';
                $lockDays    = (int)($data['lockdays'] ?? 0);
                $agingDays   = (int)($data['ddays'] ?? 0);

                $billDate = isset($data['date']) ? \Carbon\Carbon::parse($data['date']) : now();
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
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if (!empty($upsertData)) {
                $this->flushUpsert($upsertData);
                $totalProcessed += count($upsertData);
            }
        }

        $this->info("Processed {$totalProcessed} bills. Skipped {$totalSkipped}.");
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
