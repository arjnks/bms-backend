<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Services\ExternalBillingService;

class RemoveEmptyBills extends Command
{
    protected $signature = 'bms:remove-empty-bills';
    protected $description = 'Scans the database and permanently deletes bills that have 0 line items in the ERP system.';

    public function handle(ExternalBillingService $service)
    {
        $this->info("Starting cleanup of empty bills...");
        $bills = Bill::all();
        $deleted = 0;
        
        $bar = $this->output->createProgressBar(count($bills));
        $bar->start();

        foreach ($bills as $bill) {
            $items = $service->getBillDetails($bill->invoice_no);
            if (empty($items)) {
                $bill->delete();
                $deleted++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Finished. Deleted {$deleted} empty bills from the database.");
    }
}
