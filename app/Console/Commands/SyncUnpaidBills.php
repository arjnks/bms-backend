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
    protected $description = 'Fetch unpaid historical bills and update aging balances';

    public function handle(ExternalBillingService $billingService)
    {
        $this->info("Fetching unpaid bills from external API (with pagination)...");
        
        $unpaidBills = $billingService->getUnpaidBills();
        
        if (empty($unpaidBills)) {
            $this->info("No unpaid bills found or API returned empty.");
            return;
        }

        $this->info("Found " . count($unpaidBills) . " unpaid bills. Processing...");

        // Get all customer codes
        $cucodes = collect($unpaidBills)->pluck('cucode')->filter()->unique()->toArray();
        $customers = Customer::whereIn('external_cucode', $cucodes)->get()->keyBy('external_cucode');

        $upsertData = [];
        $skipped = 0;

        foreach ($unpaidBills as $data) {
            $cucode = $data['cucode'] ?? null;
            if (!$cucode || !isset($customers[$cucode])) {
                $skipped++;
                continue;
            }

            $customer = $customers[$cucode];
            $invoiceNo = $data['billno'] ?? null;
            if (!$invoiceNo) {
                $skipped++;
                continue;
            }

            $netAmount = (float)($data['netamount'] ?? 0);
            $amtReceived = (float)($data['amtreceived'] ?? 0);
            $isSettled = ($data['settled'] ?? 'N') === 'Y';
            $agingDays = (int)($data['ddays'] ?? 0);
            $lockDays = (int)($data['lockdays'] ?? 0);
            
            $billDate = isset($data['date']) ? Carbon::parse($data['date']) : now();
            
            // Assume due date is bill date + lock days
            $dueDate = (clone $billDate)->addDays($lockDays);

            $upsertData[] = [
                'customer_id'     => $customer->id,
                'invoice_no'      => $invoiceNo,
                'bill_date'       => $billDate->format('Y-m-d'),
                'due_date'        => $dueDate->format('Y-m-d'),
                'subtotal'        => $netAmount,
                'gst_total'       => 0, // Not provided in this API
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
        }

        if (!empty($upsertData)) {
            $this->info("Upserting " . count($upsertData) . " bills into database...");
            
            // Use chunks to avoid memory issues
            foreach (array_chunk($upsertData, 500) as $chunk) {
                DB::table('bills')->upsert(
                    $chunk,
                    ['invoice_no'], // Unique columns
                    ['amount_received', 'is_settled', 'aging_days', 'lock_days', 'status', 'payment_status', 'updated_at'] // Columns to update
                );
            }
        }

        $this->info("Sync completed. Skipped $skipped records (missing customer or invoice no).");
    }
}
