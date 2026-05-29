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
        $today = Carbon::today()->toDateString();
        $todayStr = "'" . $today . "'";
        
        $stats = \Illuminate\Support\Facades\DB::table('bills')
            ->selectRaw("
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) <= 30 THEN 1 ELSE 0 END) as count_0_30,
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) <= 30 THEN grand_total ELSE 0 END) as total_0_30,
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) > 30 AND DATEDIFF($todayStr, due_date) <= 60 THEN 1 ELSE 0 END) as count_31_60,
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) > 30 AND DATEDIFF($todayStr, due_date) <= 60 THEN grand_total ELSE 0 END) as total_31_60,
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) > 60 THEN 1 ELSE 0 END) as count_60_plus,
                SUM(CASE WHEN DATEDIFF($todayStr, due_date) > 60 THEN grand_total ELSE 0 END) as total_60_plus
            ")
            ->whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->where('due_date', '<', $today)
            ->first();

        return response()->json([
            '0_30_days' => ['count' => (int)($stats->count_0_30 ?? 0), 'total_amount' => (float)($stats->total_0_30 ?? 0)],
            '31_60_days' => ['count' => (int)($stats->count_31_60 ?? 0), 'total_amount' => (float)($stats->total_31_60 ?? 0)],
            '60_plus_days' => ['count' => (int)($stats->count_60_plus ?? 0), 'total_amount' => (float)($stats->total_60_plus ?? 0)],
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
