<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBillsController extends Controller
{
    /**
     * Trigger a full ERP bill sync for all customers.
     * Runs synchronously on the Railway server.
     * POST /admin/sync/bills
     */
    public function syncBills()
    {
        $baseUrl = rtrim(config('services.external_billing.url', ''), '/');

        if (empty($baseUrl)) {
            return response()->json(['error' => 'EXTERNAL_BILLING_URL not configured.'], 503);
        }

        $fromDate = now()->subMonths(24)->format('Y-m-d');
        $toDate   = now()->format('Y-m-d');

        // Include customers with either external_cucode or customer_code set
        $customers = Customer::where(function($q) {
            $q->whereNotNull('external_cucode')
              ->orWhereNotNull('customer_code');
        })->get()->map(function ($c) {
            // Normalise: use external_cucode, fall back to customer_code
            $c->_effective_cucode = $c->external_cucode ?: $c->customer_code;
            return $c;
        })->filter(fn($c) => !empty($c->_effective_cucode));

        $totalSynced = 0;
        $errors = 0;

        // Process in batches of 10 to avoid Ngrok rate limits
        foreach ($customers->chunk(10) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $baseUrl, $fromDate, $toDate) {
                return $chunk->map(fn($c) =>
                    $pool->as((string)$c->id)
                         ->timeout(12)
                         ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                         ->asMultipart()
                         ->post("$baseUrl/API/announcements/bill_master.php", [
                             ['name' => 'cucode',    'contents' => $c->_effective_cucode],
                             ['name' => 'from_date', 'contents' => $fromDate],
                             ['name' => 'to_date',   'contents' => $toDate],
                         ])
                );
            });

            foreach ($responses as $customerId => $response) {
                try {
                    if (!($response instanceof \Illuminate\Http\Client\Response) || !$response->successful()) {
                        $errors++;
                        continue;
                    }
                    $data = $response->json();
                    if (!isset($data['data']) || !is_array($data['data'])) continue;

                    // Also persist the effective cucode so future syncs work without fallback
                    $customer = $chunk->firstWhere('id', $customerId);
                    if ($customer && empty($customer->external_cucode)) {
                        $customer->update(['external_cucode' => $customer->_effective_cucode]);
                    }

                    foreach ($data['data'] as $bill) {
                        $invoiceNo = $bill['BILLNO'] ?? $bill['BN'] ?? null;
                        if (!$invoiceNo) continue;

                        $billDate = isset($bill['DATE'])
                            ? \Carbon\Carbon::parse($bill['DATE'])
                            : now();

                        // Bills older than 30 days with no payment = overdue
                        $dueDate      = $billDate->copy()->addDays(30);
                        $isPastDue    = $dueDate->isPast();
                        $paymentStatus = $isPastDue ? 'unpaid' : 'unpaid'; // keep unpaid; status field tracks overdue

                        Bill::updateOrCreate(
                            ['invoice_no' => (string)$invoiceNo],
                            [
                                'customer_id'    => $customerId,
                                'bill_date'      => $billDate->format('Y-m-d'),
                                'due_date'       => $dueDate->format('Y-m-d'),
                                'grand_total'    => $bill['NETAMOUNT'] ?? 0,
                                'subtotal'       => $bill['NETAMOUNT'] ?? 0,
                                'gst_total'      => 0,
                                'status'         => $isPastDue ? 'overdue' : 'unpaid',
                                'payment_status' => 'unpaid',
                            ]
                        );
                        $totalSynced++;
                    }
                } catch (\Exception $e) {
                    Log::error('SyncBillsController error', ['customer_id' => $customerId, 'error' => $e->getMessage()]);
                    $errors++;
                }
            }

            // Small delay to avoid hammering Ngrok
            usleep(200000); // 200ms
        }

        return response()->json([
            'status'       => 'done',
            'total_synced' => $totalSynced,
            'errors'       => $errors,
            'bills_in_db'  => Bill::count(),
        ]);
    }

    /**
     * Quick status check.
     * GET /admin/sync/bills/status
     */
    public function billSyncStatus()
    {
        return response()->json([
            'bills_in_db'  => Bill::count(),
            'customers'    => Customer::whereNotNull('external_cucode')->count(),
            'last_synced'  => Bill::max('updated_at'),
        ]);
    }
}
