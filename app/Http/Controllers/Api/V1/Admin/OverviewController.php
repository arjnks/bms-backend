<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Bill;
use App\Models\ReminderLog;
use Carbon\Carbon;

class OverviewController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Dues This Month
        $totalOutstanding = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->where('bill_date', '>=', $thisMonth)
            ->sum('grand_total');

        // Bills Sent Today (Live from ERP, falls back to local DB if ERP unreachable)
        $billsToday = 0;
        try {
            $erpUrl = rtrim(config('services.external_billing.url', ''), '/');
            if ($erpUrl) {
                $res = \Illuminate\Support\Facades\Http::timeout(5)->withHeaders([
                    'ngrok-skip-browser-warning' => 'true'
                ])->get($erpUrl . '/API/announcements/bill_master.php');
                if ($res->successful()) {
                    $bills = $res->json();
                    if (is_array($bills)) {
                        $billsToday = collect($bills)->filter(function($b) use ($today) {
                            try {
                                return isset($b['bill_date']) && Carbon::parse($b['bill_date'])->isSameDay($today);
                            } catch (\Exception $e) { return false; }
                        })->count();
                    }
                }
            }
        } catch (\Exception $e) {
            // ERP unreachable — silently fall through to local count
        }
        if ($billsToday === 0) {
            $billsToday = Bill::whereDate('bill_date', $today)->count();
        }

        // Total Customers
        $totalCustomers = \App\Models\User::where('role', 'customer')->count();

        // Overdue Count
        $overdueCount = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->whereDate('due_date', '<', $today)
            ->count();

        // Collection Rate (paid bills vs total bills this month)
        $totalBillsThisMonth = Bill::where('bill_date', '>=', $thisMonth)->count();
        $paidBillsThisMonth = Bill::where('payment_status', 'paid')
            ->where('bill_date', '>=', $thisMonth)
            ->count();

        $collectionRate = $totalBillsThisMonth > 0 
            ? round(($paidBillsThisMonth / $totalBillsThisMonth) * 100, 1) 
            : 0;

        $remindersThisMonth = ReminderLog::where('sent_at', '>=', $thisMonth)->count();

        // Chart: Payment Status Breakdown
        $paidCount = Bill::where('payment_status', 'paid')->count();
        $dueSoonCount = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->whereDate('due_date', '>=', $today)
            ->count();
        // $overdueCount is already calculated above

        $chartPaymentStatus = [
            ['name' => 'Paid', 'value' => $paidCount, 'color' => '#166534'],
            ['name' => 'Due soon', 'value' => $dueSoonCount, 'color' => '#b45309'],
            ['name' => 'Overdue', 'value' => $overdueCount, 'color' => '#c0392b'],
        ];

        // Chart: Collections (Last 6 months for overview)
        $chartCollections = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $monthLabel = $monthStart->format('M');
            
            $collected = Bill::where('payment_status', 'paid')
                ->whereBetween('payment_verified_at', [$monthStart, $monthEnd])
                ->sum('grand_total');

            $chartCollections[] = [
                'month' => $monthLabel,
                'amount' => (float) $collected,
            ];
        }

        return response()->json([
            'total_customers' => $totalCustomers,
            'total_outstanding' => (float) $totalOutstanding,
            'bills_today' => $billsToday,
            'overdue_count' => $overdueCount,
            'collection_rate' => $collectionRate,
            'reminders_this_month' => $remindersThisMonth,
            'chart_payment_status' => $chartPaymentStatus,
            'chart_collections' => $chartCollections,
        ]);
    }
}
