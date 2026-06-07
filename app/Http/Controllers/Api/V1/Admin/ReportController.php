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
            ->where('is_settled', false)
            ->where('due_date', '<', $today->toDateString())
            ->get(['due_date', 'grand_total', 'amount_received']);

        $stats = [
            'count_0_30' => 0, 'total_0_30' => 0,
            'count_31_60' => 0, 'total_31_60' => 0,
            'count_60_plus' => 0, 'total_60_plus' => 0,
        ];

        foreach ($bills as $bill) {
            $daysOverdue = Carbon::parse($bill->due_date)->diffInDays($today);
            $amt = (float) max(0, $bill->grand_total - ($bill->amount_received ?? 0));
            
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
        $now = Carbon::now();
        $collections = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            
            $collected = Bill::whereYear('bill_date', $month->year)
                ->whereMonth('bill_date', $month->month)
                ->sum('amount_received');
                
            $billCount = Bill::whereYear('bill_date', $month->year)
                ->whereMonth('bill_date', $month->month)
                ->where('amount_received', '>', 0)
                ->count();

            $collections[] = [
                'month' => $month->format('Y-m'),
                'total_collected' => (float) $collected,
                'bill_count' => $billCount
            ];
        }

        return response()->json($collections);
    }
}
