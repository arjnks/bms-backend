<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppReminderJob;
use App\Models\Bill;
use App\Models\ReminderRule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'reminders:process';
    protected $description = 'Process reminder rules and dispatch WhatsApp jobs';

    public function handle()
    {
        $this->info('Starting reminder processing...');
        $rules = ReminderRule::where('is_active', true)->where('channel', 'whatsapp')->get();
        $today = Carbon::today();
        
        $jobsDispatched = 0;

        foreach ($rules as $rule) {
            $billsQuery = Bill::with(['customer.user'])
                ->whereIn('payment_status', ['unpaid', 'proof_rejected']);

            if ($rule->trigger_type === 'before_due') {
                $targetDate = $today->copy()->addDays($rule->offset_days);
                $billsQuery->whereDate('due_date', $targetDate);
            } elseif ($rule->trigger_type === 'on_due') {
                $billsQuery->whereDate('due_date', $today);
            } elseif ($rule->trigger_type === 'after_due') {
                $targetDate = $today->copy()->subDays($rule->offset_days);
                $billsQuery->whereDate('due_date', $targetDate);
            } elseif ($rule->trigger_type === 'weekly_overdue') {
                $billsQuery->whereDate('due_date', '<', $today);
                // We will filter weekly logic in PHP to handle modulo reliably across DB engines
            }

            $bills = $billsQuery->get();

            foreach ($bills as $bill) {
                if ($rule->trigger_type === 'weekly_overdue') {
                    $daysOverdue = Carbon::parse($bill->due_date)->diffInDays($today);
                    if ($daysOverdue % 7 !== 0) {
                        continue;
                    }
                }

                // Check if we already sent a reminder for this bill + rule today
                $alreadySent = $bill->reminderLogs()
                    ->where('rule_id', $rule->id)
                    ->whereDate('sent_at', $today)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $phone = $bill->customer->user->phone;
                if (!$phone) continue;

                $amount = number_format($bill->grand_total, 2);
                $date = Carbon::parse($bill->due_date)->format('Y-m-d');
                $link = env('APP_URL') . "/portal/bills/{$bill->id}/pay";

                $message = "Hi {$bill->customer->user->name}, your payment of ₹{$amount} for {$bill->invoice_no} is due on {$date}. Pay here: {$link}";
                
                // If the rule has a custom message template, use it (replacing basic variables)
                if ($rule->message_template) {
                    $message = str_replace(
                        ['[Name]', '[amount]', '[invoice_no]', '[due_date]', '[link]'],
                        [$bill->customer->user->name, "₹{$amount}", $bill->invoice_no, $date, $link],
                        $rule->message_template
                    );
                }

                SendWhatsAppReminderJob::dispatch($phone, $message, $bill->customer_id, $bill->id, $rule->id);
                $jobsDispatched++;
            }
        }

        $this->info("Dispatched {$jobsDispatched} reminder jobs.");
        Log::info("ProcessRemindersCommand completed: {$jobsDispatched} jobs dispatched.");
    }
}
