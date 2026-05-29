<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Bill;
use App\Services\ExternalBillingService;
use Illuminate\Support\Facades\Log;

class SyncBillsCommand extends Command
{
    protected $signature = 'bms:sync-bills {--days=30 : Number of days back to sync}';
    protected $description = 'Sync all bills from ERP for all customers';

    public function handle(ExternalBillingService $billing)
    {
        $days = (int) $this->option('days');
        $fromDate = now()->subDays($days)->format('Y-m-d');
        $toDate = now()->addDays(7)->format('Y-m-d');
        
        $customers = Customer::whereNotNull('external_cucode')->get();
        $total = $customers->count();
        
        $this->info("Starting bill sync for $total customers (From $fromDate to $toDate)");
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $inserted = 0;
        
        foreach ($customers as $customer) {
            try {
                $erpBills = $billing->getBills($customer->external_cucode, $fromDate, $toDate);
                
                if (!empty($erpBills)) {
                    foreach ($erpBills as $eb) {
                        $invoiceNo = $eb['BN'] ?? null;
                        if (!$invoiceNo) continue;
                        
                        $billDate = isset($eb['DATE']['date']) ? date('Y-m-d', strtotime($eb['DATE']['date'])) : now()->format('Y-m-d');
                        $dueDate = date('Y-m-d', strtotime($billDate . ' + 30 days')); // Default 30 days due
                        
                        Bill::updateOrCreate(
                            ['invoice_no' => $invoiceNo],
                            [
                                'customer_id' => $customer->id,
                                'bill_date' => $billDate,
                                'due_date' => $dueDate,
                                'grand_total' => $eb['NETAMOUNT'] ?? 0,
                                'status' => 'unpaid',
                                'payment_status' => 'unpaid',
                                // Do not overwrite payment_status if already paid
                            ]
                        );
                        $inserted++;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync bills for cucode {$customer->external_cucode}: " . $e->getMessage());
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Synced $inserted bills across $total customers.");
    }
}
