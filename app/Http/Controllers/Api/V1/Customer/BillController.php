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
            ->whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->sum('grand_total');

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
            $lineItems = $bill->lineItems;
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

        // Cloud-hosted bills can be opened directly. Local uploads must go
        // through the signed stream route because Railway has no /storage link.
        if ($this->isExternalUrl($bill->bill_file_url)) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }

        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\Cache::put("bill_token_{$token}", [
            'id' => $bill->id,
            'customer_id' => $customerId,
        ], now()->addMinutes(30));

        $url = "/api/v1/customer/bills/stream-token/{$token}";
        return response()->json(['download_url' => $url]);
    }

    public function streamByToken(Request $request, $token, ExternalBillingService $billingService)
    {
        $data = \Illuminate\Support\Facades\Cache::get("bill_token_{$token}");
        if (!$data) {
            return response()->json(['message' => 'Invalid or expired token.'], 403);
        }

        return $this->stream($request, $data['id'], $billingService);
    }

    public function stream(Request $request, $id, ExternalBillingService $billingService)
    {
        // No customerId check because it's a signed route, but we must ensure it exists.
        $bill = Bill::with('customer.user')->findOrFail($id);

        if ($this->isExternalUrl($bill->bill_file_url)) {
            return redirect()->away($bill->bill_file_url);
        }

        $storedPath = $this->localBillPath($bill->bill_file_url);
        if ($storedPath && Storage::disk('public')->exists($storedPath)) {
            $filename = basename($storedPath) ?: 'bill_' . $this->safeBillNo($bill->invoice_no);
            $mime = Storage::disk('public')->mimeType($storedPath) ?: 'application/octet-stream';

            return Storage::disk('public')->download($storedPath, $filename, [
                'Content-Type' => $mime,
            ]);
        }

        $items = $billingService->getBillDetails((string) $bill->invoice_no);

        if (empty($items)) {
            return response()->json(['message' => 'No file associated and ERP fetch failed.'], 404);
        }

        $customerName = $bill->customer->user->name ?? 'Customer';
        $format = $bill->customer->preferred_bill_format ?? 'pdf';
        
        $billNoStr = $items[0]['BILLNO'] ?? (string) $bill->invoice_no;
        $billDate = $items[0]['BILLDATE'] ?? ($bill->bill_date ? $bill->bill_date->format('Y-m-d') : now()->format('Y-m-d'));

        $safeBillNo = $this->safeBillNo($billNoStr);

        switch ($format) {
            case 'pdf':
                $path     = $billingService->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename = "bill_{$safeBillNo}.pdf";
                $mime     = 'application/pdf';
                break;

            case 'csv':
                $path     = $billingService->generateCsv($items, $bill->invoice_no, $billDate);
                $filename = "bill_{$safeBillNo}.csv";
                $mime     = 'text/csv';
                break;

            default: // excel
                $path     = $billingService->generateExcel($items, $billNoStr, $billDate);
                $filename = "bill_{$safeBillNo}.xlsx";
                $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(15));
        return redirect()->away($url);
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

        $path = $request->file('screenshot')->store('proofs', 'public');

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
