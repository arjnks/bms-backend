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
        $customers = Customer::with('user')->where(function($q) {
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

                    $billingService = app(\App\Services\ExternalBillingService::class);

                    foreach ($data['data'] as $billData) {
                        $invoiceNo = $billData['BILLNO'] ?? $billData['BN'] ?? null;
                        if (!$invoiceNo) continue;

                        $billDate = isset($billData['DATE'])
                            ? \Carbon\Carbon::parse($billData['DATE'])
                            : now();

                        // Bills older than 30 days with no payment = overdue
                        $dueDate      = $billDate->copy()->addDays(30);
                        $isPastDue    = $dueDate->isPast();

                        $bill = Bill::firstOrNew(['invoice_no' => (string)$invoiceNo]);
                        $bill->customer_id = $customerId;
                        $bill->bill_date = $billDate->format('Y-m-d');
                        $bill->due_date = $dueDate->format('Y-m-d');
                        $bill->grand_total = $billData['NETAMOUNT'] ?? 0;
                        $bill->subtotal = $billData['NETAMOUNT'] ?? 0;
                        if (!$bill->exists) {
                            $bill->payment_status = 'unpaid';
                            $bill->status = $isPastDue ? 'overdue' : 'unpaid';
                        } else {
                            if ($bill->payment_status === 'unpaid' && $isPastDue) {
                                $bill->status = 'overdue';
                            }
                        }
                        $bill->save();

                        // Check if we need to fetch from ERP (missing file OR missing line items)
                        $needsItems = false;
                        try {
                            $needsItems = $bill->lineItems()->count() === 0;
                        } catch (\Exception $e) {} // Ignore if table missing locally during sync

                        if (empty($bill->bill_file_url) || $needsItems) {
                            // Extract numeric ID
                            preg_match('/(\d+)$/', $bill->invoice_no, $matches);
                            $numericId = (int) ($matches[1] ?? $bill->invoice_no);

                            $items = $billingService->getBillDetails($numericId);
                            
                            if (!empty($items)) {
                                if ($needsItems) {
                                    foreach ($items as $item) {
                                        try {
                                            $bill->lineItems()->create([
                                                'product_name' => $item['ITEMNAME'] ?? 'Unknown Item',
                                                'hsn_code'     => $item['HSNCODE'] ?? null,
                                                'qty'          => $item['QUANTITY'] ?? 1,
                                                'unit'         => $item['UNIT'] ?? 'NOS',
                                                'rate'         => $item['SRATE'] ?? 0,
                                                'gst_pct'      => $item['GSTRATE'] ?? 0,
                                                'line_total'   => $item['TOTALAMOUNT'] ?? 0,
                                            ]);
                                        } catch (\Exception $e) {} // Ignore if table missing
                                    }
                                }

                                if (empty($bill->bill_file_url)) {
                                    $customerName = $customer->user->name ?? 'Customer';
                                    $format = $customer->preferred_bill_format ?? 'pdf';
                                    
                                    $billNoStr = $items[0]['BILLNO'] ?? (string) $bill->invoice_no;
                                    $safeBillNo = str_replace(['/', '\\'], '_', $billNoStr);
                                    $bDate = $items[0]['BILLDATE'] ?? $billDate->format('Y-m-d');

                                    switch ($format) {
                                        case 'pdf':
                                            $path     = $billingService->generatePdf($items, $billNoStr, $bDate, $customerName);
                                            $filename = "bills/{$customer->_effective_cucode}/bill_{$safeBillNo}.pdf";
                                            $mime     = 'application/pdf';
                                            break;
                                        case 'csv':
                                            $path     = $billingService->generateCsv($items, $billNoStr);
                                            $filename = "bills/{$customer->_effective_cucode}/bill_{$safeBillNo}.csv";
                                            $mime     = 'text/csv';
                                            break;
                                        default: // excel
                                            $path     = $billingService->generateExcel($items, $billNoStr, $bDate);
                                            $filename = "bills/{$customer->_effective_cucode}/bill_{$safeBillNo}.xlsx";
                                            $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                                            break;
                                    }

                                    try {
                                        $file = new \Illuminate\Http\File($path);
                                        \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs('', $file, $filename, [
                                            'visibility' => 'public',
                                            'ContentType' => $mime
                                        ]);
                                        
                                        $url = \Illuminate\Support\Facades\Storage::disk('s3')->url($filename);
                                        $bill->update(['bill_file_url' => $url]);
                                    } catch (\Exception $e) {
                                        Log::error('S3 Upload Failed', ['error' => $e->getMessage()]);
                                    }
                                    
                                    @unlink($path);
                                }
                            }
                        }

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
