<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Bill;

#[Signature('bms:update-bill-statuses')]
#[Description('Update bill statuses based on aging_days and lock_days')]
class UpdateBillStatuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Updating bill statuses...");
        
        $now = now()->toDateString();
        
        // Mark bills whose due date has passed as overdue
        $overdueCount = Bill::where('status', '!=', 'paid')
            ->where('is_settled', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $now)
            ->update(['status' => 'overdue']);
            
        // All remaining unpaid bills (due date today or in the future, or no due date)
        $unpaidCount = Bill::where('status', '!=', 'paid')
            ->where('is_settled', false)
            ->where(function($q) use ($now) {
                $q->whereNull('due_date')
                  ->orWhereDate('due_date', '>=', $now);
            })
            ->update(['status' => 'unpaid']);

        $this->info("Updated {$overdueCount} bills to overdue.");
        $this->info("Updated {$unpaidCount} bills to unpaid.");
    }
}
