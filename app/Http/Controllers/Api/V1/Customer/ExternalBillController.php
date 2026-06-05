<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\ExternalBillingService;
use Illuminate\Http\Request;

class ExternalBillController extends Controller
{
    protected ExternalBillingService $billing;

    public function __construct(ExternalBillingService $billing)
    {
        $this->billing = $billing;
    }

    private function getCustomer(Request $request)
    {
        $customer = $request->user()->customer;
        if (!$customer) {
            abort(403, 'No customer profile found.');
        }
        // Use external_cucode first, fall back to customer_code (both store the ERP cucode)
        $cucode = $customer->external_cucode ?: $customer->customer_code;
        if (!$cucode) {
            abort(422, 'Your account is not linked to the billing system yet. Please contact admin.');
        }
        // Normalise so all downstream methods always read external_cucode
        $customer->external_cucode = $cucode;
        return $customer;
    }

    /**
     * List bills for the logged-in customer within a date range.
     * GET /customer/external-bills?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD
     */
    public function index(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date_format:Y-m-d',
            'to_date'   => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);

        $customer = $this->getCustomer($request);

        $bills = $this->billing->getBills(
            $customer->external_cucode,
            $request->from_date,
            $request->to_date
        );

        // Sort by date descending
        usort($bills, fn($a, $b) => strcmp($b['DATE'] ?? '', $a['DATE'] ?? ''));

        return response()->json([
            'status' => 'success',
            'count'  => count($bills),
            'data'   => $bills,
        ]);
    }

    /**
     * Get line items for a specific bill.
     * GET /customer/external-bills/{billno}
     */
    public function show(Request $request, string $billno)
    {
        $this->getCustomer($request); // validates customer is linked

        $items = $this->billing->getBillDetails($billno);

        if (empty($items)) {
            return response()->json(['status' => 'error', 'message' => 'Bill not found or no items.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'count'  => count($items),
            'data'   => $items,
            'summary' => [
                'bill_no'    => $items[0]['BILLNO'] ?? '',
                'bill_date'  => $items[0]['BILLDATE'] ?? '',
                'net_amount' => $items[0]['NETAMOUNT'] ?? 0,
            ],
        ]);
    }

    /**
     * Get a signed URL to download this ERP bill directly from Railway (bypasses Vercel proxy).
     * GET /customer/external-bills/{billno}/download-url
     */
    public function downloadUrl(Request $request, string $billno)
    {
        $customer = $request->user()->customer;
        if (!$customer) {
            return response()->json(['message' => 'Not a customer profile'], 403);
        }

        $requestedFormat = $request->query('format');

        $bill = \App\Models\Bill::where('invoice_no', (string)$billno)
                  ->where('customer_id', $customer->id)
                  ->first();
                  
        if ($bill && $bill->bill_file_url && !$requestedFormat) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }
        
        $format = $requestedFormat ?? $customer->preferred_bill_format ?? 'excel';

        // Generate a secure one-time token and store it in cache for 15 minutes
        $token = \Illuminate\Support\Str::random(40);
        \Illuminate\Support\Facades\Cache::put("bill_dl_{$token}", ['billno' => $billno, 'format' => $format], now()->addMinutes(15));

        // Return the tokenized download URL
        $url = "/api/v1/customer/external-bills/stream-token/{$token}";
        return response()->json(['download_url' => $url]);
    }

    /**
     * Token stream handler — validates the cache token instead of URL signature.
     */
    public function streamByToken(Request $request, string $token)
    {
        $data = \Illuminate\Support\Facades\Cache::pull("bill_dl_{$token}");

        if (!$data) {
            abort(403, 'Download link has expired or is invalid. Please request a new one.');
        }

        $billno = $data['billno'];
        $format = $data['format'];

        $r2Path = $this->billing->getCachedFilePath($format, $billno);
        $safeBillNo = str_replace(['/', '\\'], '_', $billno);
        
        if (\Illuminate\Support\Facades\Storage::disk('r2')->exists($r2Path)) {
            $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($r2Path, now()->addMinutes(15));
            return redirect()->away($url);
        }

        $items  = $this->billing->getBillDetails($billno);

        if (empty($items)) {
            abort(404, 'Bill details not found in ERP.');
        }

        $billNoStr    = (string)($items[0]['BILLNO'] ?? $billno);
        $billDate     = $items[0]['BILLDATE'] ?? now()->format('Y-m-d');
        $customerName = '';

        switch ($format) {
            case 'pdf':
                $r2PathReturned = $this->billing->generatePdf($items, $billNoStr, $billDate, $customerName);
                break;
            case 'csv':
                $r2PathReturned = $this->billing->generateCsv($items, $billNoStr, $billDate);
                break;
            default:
                $r2PathReturned = $this->billing->generateExcel($items, $billNoStr, $billDate);
                break;
        }

        try {
            $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($r2PathReturned, now()->addMinutes(15));
            return redirect()->away($url);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('R2 URL generation failed in ExternalBillController', ['error' => $e->getMessage()]);
            abort(500, 'File generation failed.');
        }
    }
}
