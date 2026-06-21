<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Services\ExternalBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    private function isExternalUrl(?string $url): bool
    {
        return is_string($url) && preg_match('/^https?:\/\//i', $url);
    }

    private function localBillPath(?string $path): ?string
    {
        if (!$path || $this->isExternalUrl($path)) {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path;
    }

    private function safeBillNo(string $billNo): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $billNo) ?: 'bill';
    }

    private function erpBillNoCandidates(Bill $bill): array
    {
        $candidates = [
            (string) $bill->invoice_no,
            (string) $bill->id,
        ];

        preg_match('/(\d+)$/', (string) $bill->invoice_no, $matches);
        if (!empty($matches[1])) {
            $candidates[] = $matches[1];
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function erpItemsBelongToCustomer(array $items, Bill $bill): bool
    {
        if (empty($items)) {
            return false;
        }

        $itemCustomerCode = $items[0]['cucode'] ?? $items[0]['CUCODE'] ?? null;
        if (!$itemCustomerCode) {
            return true;
        }

        $expectedCodes = array_filter([
            $bill->customer?->external_cucode,
            $bill->customer?->customer_code,
        ]);

        return in_array((string) $itemCustomerCode, array_map('strval', $expectedCodes), true);
    }

    private function fetchErpLineItems(Bill $bill, ExternalBillingService $billingService): array
    {
        foreach ($this->erpBillNoCandidates($bill) as $candidate) {
            $items = $billingService->getBillDetails($candidate);

            if ($this->erpItemsBelongToCustomer($items, $bill)) {
                return $items;
            }
        }

        return [];
    }

    private function cacheLineItems(Bill $bill, array $items): void
    {
        foreach ($items as $item) {
            try {
                $bill->lineItems()->create([
                    'product_name' => $item['ITEMNAME'] ?? 'Unknown Item',
                    'hsn_code'     => $item['HSNCODE'] ?? null,
                    'qty'          => $item['QUANTITY'] ?? 1,
                    'unit'         => $item['UNIT'] ?? $item['PACKING'] ?? 'NOS',
                    'rate'         => $item['SRATE'] ?? 0,
                    'gst_pct'      => $item['GSTRATE'] ?? 0,
                    'line_total'   => $item['TOTALAMOUNT'] ?? 0,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to cache ERP line item', [
                    'bill_id' => $bill->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getCustomerId(Request $request)
    {
        // Get the customer ID associated with the currently authenticated user
        $customer = $request->user()->customer;
        if (!$customer) {
            abort(403, 'User does not have an associated customer profile.');
        }
        return $customer->id;
    }

    public function index(Request $request)
    {
        $customer = $request->user()->customer;
        if (!$customer || empty($customer->external_cucode)) {
            return response()->json([
                'has_outstanding'    => false,
                'outstanding_amount' => 0,
                'bills'              => [],
            ]);
        }

        $baseUrl = rtrim(config('services.external_billing.url'), '/');
        $fromDate = now()->subYears(10)->format('Y-m-d');
        $toDate = now()->format('Y-m-d');

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->asMultipart()
                ->post("{$baseUrl}/API/announcements/bill_master.php", [
                    'cucode' => $customer->external_cucode,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ]);

            if (!$response->successful()) {
                throw new \Exception('ERP API HTTP ' . $response->status());
            }
            
            $data = $response->json();
            $erpBills = $data['data'] ?? [];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("ERP Fetch failed in portal", ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch live data from ERP.'], 500);
        }

        // Fetch local overlay data (bills where they submitted payment)
        $localBills = Bill::where('customer_id', $customer->id)
            ->whereIn('payment_status', ['payment_submitted', 'paid', 'proof_rejected'])
            ->get()
            ->keyBy('invoice_no');

        // Fetch real lockdays from the local cache of ERP statuses
        $invoiceNos = array_column($erpBills, 'BILLNO') ?: array_column($erpBills, 'BN');
        $erpStatuses = \App\Models\ErpBillStatus::whereIn('billno', $invoiceNos)->get()->keyBy('billno');

        $bills = [];
        $outstanding_amount = 0;

        foreach ($erpBills as $bill) {
            $invoiceNo = $bill['BILLNO'] ?? $bill['BN'] ?? null;
            if (!$invoiceNo) continue;

            $netAmount  = (float) ($bill['NETAMOUNT'] ?? 0);
            $amtReceived = (float) ($bill['AMOUNTRECIEVED'] ?? $bill['AMTRECEIVED'] ?? $bill['AMOUNT_RECEIVED'] ?? 0);
            
            $isSettled = ($bill['SETTLED'] ?? 'N') === 'Y';
            if (!isset($bill['SETTLED']) && $netAmount > 0 && $amtReceived >= $netAmount) {
                $isSettled = true;
            }

            $erpStat = $erpStatuses->get($invoiceNo);
            $lockDays = $erpStat ? (int) $erpStat->lockdays : 15;

            $billDate = isset($bill['DATE']) ? \Carbon\Carbon::parse($bill['DATE']) : now();
            $dueDate = (clone $billDate)->addDays($lockDays);
            
            $localOverlay = $localBills->get($invoiceNo);
            
            $status = $isSettled ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'unpaid');
            $paymentStatus = $isSettled ? 'paid' : 'unpaid';
            $amountToPay = $isSettled ? 0 : max(0, $netAmount - $amtReceived);
            
            // Apply Local Overlay
            if ($localOverlay) {
                $id = $localOverlay->id; 
                if (!$isSettled) {
                    if (in_array($localOverlay->payment_status, ['payment_submitted', 'paid'])) {
                        $paymentStatus = $localOverlay->payment_status;
                        $status = $localOverlay->payment_status === 'paid' ? 'paid' : 'pending_verification';
                        
                        $submittedAmt = (float) ($localOverlay->amount_submitted ?? 0);
                        $amountToPay = max(0, $netAmount - $amtReceived - $submittedAmt);
                        
                        // If they paid it entirely, force paid status if verified
                        if ($amountToPay <= 0 && $localOverlay->payment_status === 'paid') {
                            $status = 'paid';
                        }
                    } elseif ($localOverlay->payment_status === 'proof_rejected') {
                        $paymentStatus = 'proof_rejected';
                        // We do NOT subtract amount_submitted because the proof was rejected
                    }
                }
            } else {
                // To allow downloads/views, we MUST have a local bill record.
                // We create it silently so the frontend has an ID to route to.
                $localRecord = Bill::firstOrCreate(
                    ['invoice_no' => $invoiceNo],
                    [
                        'customer_id' => $customer->id,
                        'bill_date' => $billDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'subtotal' => $netAmount,
                        'grand_total' => $netAmount,
                        'amount_received' => $amtReceived,
                        'is_settled' => $isSettled,
                        'status' => $status,
                        'payment_status' => $paymentStatus,
                    ]
                );
                // Also sync existing record if it wasn't an overlay
                if (!$localRecord->wasRecentlyCreated) {
                    $localRecord->update([
                        'amount_received' => $amtReceived,
                        'is_settled' => $isSettled,
                        'status' => $status,
                        'payment_status' => $paymentStatus,
                    ]);
                }
                $id = $localRecord->id;
            }

            if ($amountToPay > 0) {
                $outstanding_amount += $amountToPay;
            }

            $bills[] = [
                'id' => $id,
                'invoice_no' => $invoiceNo,
                'bill_date' => $billDate->format('d M Y'),
                'due_date' => $dueDate->format('d M Y'),
                'grand_total' => $netAmount,
                'amount_received' => $amtReceived,
                'amount_to_pay' => $amountToPay,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'is_settled' => $isSettled,
                'proof_screenshot' => $localOverlay->proof_screenshot ?? null,
            ];
        }

        // Sort bills: unpaid first, then by due_date
        usort($bills, function($a, $b) {
            $statusRank = function($s) {
                if ($s === 'overdue') return 1;
                if ($s === 'unpaid') return 2;
                if ($s === 'pending_verification' || $s === 'payment_submitted') return 3;
                return 4; // paid
            };
            
            $rankA = $statusRank($a['status']);
            $rankB = $statusRank($b['status']);
            
            if ($rankA === $rankB) {
                return strtotime($a['due_date']) <=> strtotime($b['due_date']);
            }
            return $rankA <=> $rankB;
        });

        return response()->json([
            'has_outstanding'    => $outstanding_amount > 0,
            'outstanding_amount' => $outstanding_amount,
            'bills'              => $bills,
        ]);
    }

    public function show(Request $request, $id)
    {
        $customerId = $this->getCustomerId($request);

        $bill = Bill::where('customer_id', $customerId)->findOrFail($id);

        $lineItems = collect();
        try {
            if ($bill->lineItems()->count() === 0) {
                $billingService = app(\App\Services\ExternalBillingService::class);
                $items = $this->fetchErpLineItems($bill->loadMissing('customer'), $billingService);
                
                if (!empty($items)) {
                    $lineItems = collect($items);
                    $this->cacheLineItems($bill, $items);
                }
            }
            if ($lineItems->isEmpty()) {
                $lineItems = $bill->lineItems()->get();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::warning('bill_line_items table missing on customer show', ['error' => $e->getMessage()]);
        }

        $billArray = $bill->toArray();
        $billArray['line_items'] = $lineItems->values();
        $billArray['lineItems'] = $billArray['line_items'];

        return response()->json($billArray);
    }

    public function download(Request $request, $id)
    {
        $customerId = $this->getCustomerId($request);

        $bill = Bill::where('customer_id', $customerId)->findOrFail($id);

        $requestedFormat = $request->query('format');

        // Cloud-hosted bills can be opened directly. Local uploads must go
        // through the signed stream route because Railway has no /storage link.
        if ($this->isExternalUrl($bill->bill_file_url) && !$requestedFormat) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }

        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\Cache::put("bill_token_{$token}", [
            'id' => $bill->id,
            'customer_id' => $customerId,
            'format' => $requestedFormat,
        ], now()->addMinutes(30));

        $url = "/api/v1/customer/bills/stream-token/{$token}";
        return response()->json(['download_url' => $url]);
    }

    public function streamByToken(Request $request, $token, ExternalBillingService $billingService)
    {
        \Illuminate\Support\Facades\Log::info("StreamByToken called with token: " . $token);
        $data = \Illuminate\Support\Facades\Cache::get("bill_token_{$token}");
        if (!$data) {
            return response()->json(['message' => 'Invalid or expired token.'], 403);
        }

        return $this->stream($request, $data['id'], $billingService, $data['format'] ?? null);
    }

    public function stream(Request $request, $id, ExternalBillingService $billingService, $requestedFormat = null)
    {
        \Illuminate\Support\Facades\Log::info("Stream method called for bill id: " . $id . " format: " . $requestedFormat);
        // No customerId check because it's a signed route, but we must ensure it exists.
        $bill = Bill::with('customer.user')->findOrFail($id);

        if ($this->isExternalUrl($bill->bill_file_url) && !$requestedFormat) {
            return redirect()->away($bill->bill_file_url);
        }

        // All bills are now served from R2 or generated live from ERP.
        // Legacy local-disk bills (stored as relative paths) are redirected via R2 if they happen to exist there.
        if ($bill->bill_file_url && !$this->isExternalUrl($bill->bill_file_url) && !$requestedFormat) {
            // Might be an R2 key stored as a relative path
            $r2Key = ltrim($bill->bill_file_url, '/');
            if (Storage::disk('r2')->exists($r2Key)) {
                return redirect()->away(Storage::disk('r2')->temporaryUrl($r2Key, now()->addMinutes(30)));
            }
            // Not in R2 either — fall through to live generation below
        }

        $format = $requestedFormat ?? $bill->customer->preferred_bill_format ?? 'pdf';
        $r2Path = $billingService->getCachedFilePath($format, $bill->invoice_no);
        
        if (\Illuminate\Support\Facades\Storage::disk('r2')->exists($r2Path)) {
            return redirect()->away(\Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($r2Path, now()->addMinutes(15)));
        }

        $items = $this->fetchErpLineItems($bill, $billingService);

        if (empty($items)) {
            return response()->json(['message' => 'No file associated and ERP fetch failed.'], 404);
        }

        $customerName = $bill->customer->user->name ?? 'Customer';
        
        $billNoStr = $items[0]['BILLNO'] ?? (string) $bill->invoice_no;
        $billDate = $items[0]['BILLDATE'] ?? ($bill->bill_date ? $bill->bill_date->format('Y-m-d') : now()->format('Y-m-d'));

        $safeBillNo = $this->safeBillNo($billNoStr);

        switch ($format) {
            case 'pdf':
                $localPath = $billingService->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename  = "bill_{$safeBillNo}.pdf";
                $mime      = 'application/pdf';
                break;

            case 'csv':
                $localPath = $billingService->generateCsv($items, $bill->invoice_no, $billDate);
                $filename  = "bill_{$safeBillNo}.csv";
                $mime      = 'text/csv';
                break;

            default: // excel
                $localPath = $billingService->generateExcel($items, $billNoStr, $billDate);
                $filename  = "bill_{$safeBillNo}.xlsx";
                $mime      = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }

        // The service methods now directly upload to R2 and return the $r2Path
        $r2Path = $localPath; // localPath is actually the r2Path returned by the service
        
        try {
            $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($r2Path, now()->addMinutes(15));
            return redirect()->away($url);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate secure download link. Error: ' . $e->getMessage()], 500);
        }
    }

    public function submitPayment(Request $request, $id)
    {
        $customerId = $this->getCustomerId($request);

        $bill = Bill::where('customer_id', $customerId)->findOrFail($id);

        if ($bill->payment_status === 'paid' || $bill->payment_status === 'payment_submitted') {
            return response()->json(['message' => 'Payment already submitted or paid.'], 400);
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['gpay', 'neft'])],
            'utr_number' => 'required|string',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'screenshot' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        // Upload proof directly to R2 — no local storage
        $ext    = $request->file('screenshot')->getClientOriginalExtension();
        $r2Key  = 'proofs/' . $bill->invoice_no . '_' . time() . '.' . $ext;
        Storage::disk('r2')->put(
            $r2Key,
            file_get_contents($request->file('screenshot')->getRealPath()),
            ['ContentType' => $request->file('screenshot')->getMimeType()]
        );
        $path = $r2Key; // store the R2 key; admin view generates a signed URL from this

        $bill->update([
            'payment_method'        => $validated['payment_method'],
            'utr_number'            => $validated['utr_number'],
            'proof_screenshot'      => $path,
            'payment_status'        => 'payment_submitted',
            'payment_submitted_at'  => Carbon::now(),   // actual submission time, not the typed date
            'amount_submitted'      => $validated['amount_paid'],
        ]);

        $bill->load('customer.user');
        if ($bill->customer && $bill->customer->user && $bill->customer->user->phone) {
            app(\App\Services\WhatsAppService::class)->sendTemplate($bill->customer->user->phone, 'payment_received_v1', [
                $bill->customer->user->name,
                $bill->invoice_no,
                $validated['utr_number']
            ]);
        }

        return response()->json(['message' => 'Payment proof submitted successfully', 'bill' => $bill]);
    }
}
