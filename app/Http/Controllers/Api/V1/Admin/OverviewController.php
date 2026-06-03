<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\ReminderLog;
use Carbon\Carbon;

class OverviewController extends Controller
{
    public function index()
    {
        $today     = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $thirtyAgo = Carbon::today()->subDays(30);

        // Total outstanding: mathematically accurate sum across all years
        $totalOutstanding = Bill::where('is_settled', false)
            ->sum(\Illuminate\Support\Facades\DB::raw('grand_total - IFNULL(amount_received, 0)'));

        // Bills sent today: count from local synced bills
        $billsToday = \App\Models\Bill::whereDate('bill_date', $today)->count();

        // Total customers
        $totalCustomers = \App\Models\User::where('role', 'customer')->count();

        // Overdue count: using the new aging_days and lock_days logic
        $overdueCount = Bill::where('is_settled', false)
            ->where(function ($q) {
                $q->whereColumn('aging_days', '>', 'lock_days')
                  ->orWhere('payment_status', 'overdue');
            })
            ->count();

        // Total unpaid bill count (useful for context in the UI)
        $totalUnpaid = Bill::where('is_settled', false)->count();

        // Collection Rate: paid bills / total bills (all time)
        $totalBills = Bill::count();
        $paidBills  = Bill::where('payment_status', 'paid')->count();
        $collectionRate = $totalBills > 0
            ? round(($paidBills / $totalBills) * 100, 1)
            : 0;

        $remindersThisMonth = ReminderLog::where('sent_at', '>=', $thisMonth)->count();

        // Chart: Payment Status Breakdown
        $paidCount    = $paidBills;
        $dueSoonCount = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->count();

        $chartPaymentStatus = [
            ['name' => 'Paid',     'value' => $paidCount,    'color' => '#166534'],
            ['name' => 'Due soon', 'value' => $dueSoonCount, 'color' => '#b45309'],
            ['name' => 'Overdue',  'value' => $overdueCount, 'color' => '#c0392b'],
        ];

        // Chart: Collections (last 6 months)
        $chartCollections = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd   = Carbon::now()->subMonths($i)->endOfMonth();
            $collected  = Bill::where('payment_status', 'paid')
                ->whereBetween('payment_verified_at', [$monthStart, $monthEnd])
                ->sum('grand_total');
            $chartCollections[] = [
                'month'  => $monthStart->format('M'),
                'amount' => (float) $collected,
            ];
        }

        return response()->json([
            'total_customers'      => $totalCustomers,
            'total_outstanding'    => (float) $totalOutstanding,
            'bills_today'          => $billsToday,
            'overdue_count'        => $overdueCount,
            'total_unpaid'         => $totalUnpaid,
            'total_bills'          => $totalBills,
            'collection_rate'      => $collectionRate,
            'reminders_this_month' => $remindersThisMonth,
            'chart_payment_status' => $chartPaymentStatus,
            'chart_collections'    => $chartCollections,
        ]);
    }
}
