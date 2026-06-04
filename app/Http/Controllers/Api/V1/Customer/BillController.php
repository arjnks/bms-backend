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
        $customerId = $this->getCustomerId($request);

        $billsQuery = Bill::where('customer_id', $customerId);
        
        $outstanding_amount = (clone $billsQuery)
            ->where('is_settled', false)
            ->sum(\Illuminate\Support\Facades\DB::raw('grand_total - amount_received'));

        $bills = $billsQuery->orderBy('due_date', 'asc')->get()->map(function ($bill) {
            return array_merge($bill->toArray(), [
                'bill_date' => $bill->bill_date?->format('d M Y'),
                'due_date'  => $bill->due_date?->format('d M Y'),
            ]);
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
            if ($bill->lineItems()->count() === 0 && $bill->customer->external_cucode) {
                // Fetch from ERP on the fly
                $billingService = app(\App\Services\ExternalBillingService::class);
                $items = $billingService->getBillDetails((string) $bill->invoice_no);
                
                if (!empty($items)) {
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
                        } catch (\Exception $e) {}
                    }
                }
            }
            $lineItems = $bill->lineItems()->get();
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

        $items = $billingService->getBillDetails((string) $bill->invoice_no);

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
        ]);

        return response()->json(['message' => 'Payment proof submitted successfully', 'bill' => $bill]);
    }
}
