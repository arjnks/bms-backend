<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function aging(Request $request)
    {
        $bills = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->where('due_date', '<', Carbon::today())
            ->get(['due_date', 'grand_total']);

        $buckets = [
            '0_30_days' => ['count' => 0, 'total_amount' => 0],
            '31_60_days' => ['count' => 0, 'total_amount' => 0],
            '60_plus_days' => ['count' => 0, 'total_amount' => 0],
        ];

        foreach ($bills as $bill) {
            $days = Carbon::parse($bill->due_date)->diffInDays(Carbon::today());
            
            if ($days <= 30) {
                $buckets['0_30_days']['count']++;
                $buckets['0_30_days']['total_amount'] += (float) $bill->grand_total;
            } elseif ($days <= 60) {
                $buckets['31_60_days']['count']++;
                $buckets['31_60_days']['total_amount'] += (float) $bill->grand_total;
            } else {
                $buckets['60_plus_days']['count']++;
                $buckets['60_plus_days']['total_amount'] += (float) $bill->grand_total;
            }
        }

        return response()->json($buckets);
    }

    public function collections(Request $request)
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();

        $bills = Bill::where('payment_status', 'paid')
            ->whereNotNull('payment_verified_at')
            ->where('payment_verified_at', '>=', $startDate)
            ->get(['payment_verified_at', 'grand_total']);

        $collections = [];
        
        // Pre-fill array to guarantee exactly 12 months in chronological order
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $collections[$month] = [
                'month' => $month,
                'total_collected' => 0,
                'bill_count' => 0
            ];
        }

        foreach ($bills as $bill) {
            $month = Carbon::parse($bill->payment_verified_at)->format('Y-m');
            if (isset($collections[$month])) {
                $collections[$month]['total_collected'] += (float) $bill->grand_total;
                $collections[$month]['bill_count']++;
            }
        }

        return response()->json(array_values($collections));
    }
}
