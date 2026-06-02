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
        $today = Carbon::today();
        
        $bills = \Illuminate\Support\Facades\DB::table('bills')
            ->whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->where('due_date', '<', $today->toDateString())
            ->get(['due_date', 'grand_total']);

        $stats = [
            'count_0_30' => 0, 'total_0_30' => 0,
            'count_31_60' => 0, 'total_31_60' => 0,
            'count_60_plus' => 0, 'total_60_plus' => 0,
        ];

        foreach ($bills as $bill) {
            $daysOverdue = Carbon::parse($bill->due_date)->diffInDays($today);
            $amt = (float) $bill->grand_total;
            
            if ($daysOverdue <= 30) {
                $stats['count_0_30']++;
                $stats['total_0_30'] += $amt;
            } elseif ($daysOverdue <= 60) {
                $stats['count_31_60']++;
                $stats['total_31_60'] += $amt;
            } else {
                $stats['count_60_plus']++;
                $stats['total_60_plus'] += $amt;
            }
        }

        return response()->json([
            '0_30_days' => ['count' => $stats['count_0_30'], 'total_amount' => $stats['total_0_30']],
            '31_60_days' => ['count' => $stats['count_31_60'], 'total_amount' => $stats['total_31_60']],
            '60_plus_days' => ['count' => $stats['count_60_plus'], 'total_amount' => $stats['total_60_plus']],
        ]);
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
