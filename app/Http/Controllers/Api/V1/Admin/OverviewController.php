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
        try {
            $today     = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $thirtyAgo = Carbon::today()->subDays(30);

            // --- Live ERP financial figures (source of truth) ---
            $erpOutstanding  = null;
            $erpOverdue      = null;
            $erpBillCount    = null;
            $erpError        = null;

            try {
                $erpUrl = rtrim(env('EXTERNAL_BILLING_URL', 'https://billing.leopharma.tech'), '/') . '/API/announcements/dashboard_data.php';

                $erpResponse = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withOptions([
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                    ])
                    ->get($erpUrl);

                if ($erpResponse->successful()) {
                    $erpData        = $erpResponse->json('data', []);
                    $erpOutstanding = $erpData['total_outstandings'] ?? null;
                    $erpOverdue     = $erpData['total_overdue']      ?? null;
                    $erpBillCount   = $erpData['current_bill_count'] ?? null;
                } else {
                    $erpError = 'HTTP Status: ' . $erpResponse->status();
                }
            } catch (\Exception $e) {
                $erpError = $e->getMessage();
            }

            // Total outstanding (fallback to local DB)
            $totalOutstanding = $erpOutstanding ?? Bill::where('is_settled', false)
                ->sum(\Illuminate\Support\Facades\DB::raw('grand_total - IFNULL(amount_received, 0)'));

            // Bills sent today
            $billsToday = $erpBillCount ?? \App\Models\Bill::whereDate('bill_date', $today)->count();

            // Total customers
            $totalCustomers = \App\Models\User::where('role', 'customer')->count();

            // Overdue amount (fallback to local DB)
            $overdueAmount = $erpOverdue ?? Bill::where('is_settled', false)
                ->where(function ($q) {
                    $q->whereColumn('aging_days', '>', 'lock_days')
                      ->orWhere('payment_status', 'overdue');
                })
                ->sum(\Illuminate\Support\Facades\DB::raw('grand_total - IFNULL(amount_received, 0)'));

            // Overdue count
            $overdueCount = Bill::where('is_settled', false)
                ->where(function ($q) {
                    $q->whereColumn('aging_days', '>', 'lock_days')
                      ->orWhere('payment_status', 'overdue');
                })
                ->count();

            // Total unpaid bill count
            $totalUnpaid = Bill::where('is_settled', false)->count();

            // Collection Rate
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

            // Recent bills logic for the dashboard to match the UI expectation
            $recentBills = Bill::where('is_settled', false)
                ->with('customer.user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($b) {
                    return [
                        'id' => $b->id,
                        'invoice_no' => $b->invoice_no,
                        'customer_name' => $b->customer?->user?->name ?? 'Unknown',
                        'customer_id' => $b->customer_id,
                        'payment_status' => $b->payment_status,
                        'grand_total' => $b->grand_total,
                        'amount_received' => $b->amount_received,
                        'due_amount' => $b->grand_total - ($b->amount_received ?? 0),
                        'bill_date' => \Carbon\Carbon::parse($b->bill_date)->format('d M Y'),
                        'updated_at' => $b->updated_at->diffForHumans(),
                    ];
                });

            // Top overdue accounts
            $topOverdue = Bill::select('customer_id', \Illuminate\Support\Facades\DB::raw('SUM(grand_total - IFNULL(amount_received, 0)) as total_due'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as bill_count'))
                ->where('is_settled', false)
                ->groupBy('customer_id')
                ->orderByDesc('total_due')
                ->limit(5)
                ->with('customer.user:id,name')
                ->get()
                ->map(fn ($row) => [
                    'customer_id'   => $row->customer_id,
                    'customer_name' => $row->customer?->user?->name ?? 'Unknown',
                    'total_due'     => round($row->total_due, 2),
                    'bill_count'    => $row->bill_count,
                ]);

            return response()->json([
                'total_customers'      => $totalCustomers,
                'total_outstanding'    => (float) $totalOutstanding,
                'dues_this_month'      => (float) $totalOutstanding, // UI uses this first
                'bills_today'          => $billsToday,
                'overdue_count'        => $overdueCount,
                'overdue_amount'       => (float) $overdueAmount,
                'total_unpaid'         => $totalUnpaid,
                'total_bills'          => $totalBills,
                'collection_rate'      => $collectionRate,
                'reminders_this_month' => $remindersThisMonth,
                'chart_payment_status' => $chartPaymentStatus,
                'chart_collections'    => $chartCollections,
                'recent_bills'         => $recentBills,
                'top_overdue'          => $topOverdue,
                'erp_live'             => $erpOutstanding !== null,
                'erp_error'            => $erpError,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Exception Caught: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
