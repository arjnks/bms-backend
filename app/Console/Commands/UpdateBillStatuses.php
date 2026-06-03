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
        $threeDaysFromNow = now()->addDays(3)->toDateString();
        
        // Find bills that are overdue (due date is in the past)
        $overdueCount = Bill::where('status', '!=', 'paid')
            ->where('is_settled', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $now)
            ->update(['status' => 'overdue']);
            
        // Find bills that are nearing due date (within 3 days from today)
        $dueSoonCount = Bill::where('status', '!=', 'paid')
            ->where('is_settled', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $now)
            ->whereDate('due_date', '<=', $threeDaysFromNow)
            ->update(['status' => 'due_soon']);
            
        // Update remaining unpaid bills (due date is more than 3 days away, or null)
        $unpaidCount = Bill::where('status', '!=', 'paid')
            ->where('is_settled', false)
            ->where(function($q) use ($threeDaysFromNow) {
                $q->whereNull('due_date')
                  ->orWhereDate('due_date', '>', $threeDaysFromNow);
            })
            ->update(['status' => 'unpaid']);

        $this->info("Updated {$overdueCount} bills to overdue.");
        $this->info("Updated {$dueSoonCount} bills to due_soon.");
        $this->info("Updated {$unpaidCount} bills to unpaid.");
    }
}
