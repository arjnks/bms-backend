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

        preg_match('/(\d+)$/', $billno, $matches);
        $numericId = (int) ($matches[1] ?? $billno);

        $items = $this->billing->getBillDetails($numericId);

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
        $customer = $this->getCustomer($request);
        
        $bill = \App\Models\Bill::where('invoice_no', (string)$billno)
                  ->where('customer_id', $customer->id)
                  ->first();
                  
        if ($bill && $bill->bill_file_url) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }
        
        $format   = $customer->preferred_bill_format ?? 'excel';

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'external.bills.stream',
            now()->addMinutes(15),
            ['billno' => $billno, 'format' => $format]
        );
        return response()->json(['download_url' => $url]);
    }

    /**
     * Signed stream handler — no auth needed, URL signature validates access.
     * GET /customer/external-bills/{billno}/stream  (signed)
     */
    public function stream(Request $request, string $billno)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Download link has expired. Please request a new one.');
        }

        preg_match('/(\d+)$/', $billno, $matches);
        $numericId = (int) ($matches[1] ?? $billno);

        $format = $request->query('format', 'excel');
        $r2Path = $this->billing->getCachedFilePath($format, $billno);
        $safeBillNo = str_replace(['/', '\\'], '_', $billno);
        
        if (\Illuminate\Support\Facades\Storage::disk('r2')->exists($r2Path)) {
            $ext = $format === 'excel' ? 'xlsx' : $format;
            return \Illuminate\Support\Facades\Storage::disk('r2')->download($r2Path, "bill_{$safeBillNo}.{$ext}");
        }

        $items  = $this->billing->getBillDetails($numericId);

        if (empty($items)) {
            abort(404, 'Bill details not found in ERP.');
        }

        $billNoStr    = (string)($items[0]['BILLNO'] ?? $billno);
        $billDate     = $items[0]['BILLDATE'] ?? now()->format('Y-m-d');
        $customerName = '';

        $safeBillNo = str_replace(['/', '\\'], '_', $billNoStr);

        switch ($format) {
            case 'pdf':
                $path     = $this->billing->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename = "bill_{$safeBillNo}.pdf";
                $mime     = 'application/pdf';
                break;
            case 'csv':
                $path     = $this->billing->generateCsv($items, $billNoStr);
                $filename = "bill_{$safeBillNo}.csv";
                $mime     = 'text/csv';
                break;
            default:
                $path     = $this->billing->generateExcel($items, $billNoStr, $billDate);
                $filename = "bill_{$safeBillNo}.xlsx";
                $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return \Illuminate\Support\Facades\Storage::disk('r2')->download($path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Download bill in customer's preferred format.
     * GET /customer/external-bills/{billno}/download
     */
    public function download(Request $request, string $billno)
    {
        $customer = $this->getCustomer($request);
        
        $bill = \App\Models\Bill::where('invoice_no', (string)$billno)
                  ->where('customer_id', $customer->id)
                  ->first();
                  
        if ($bill && $bill->bill_file_url) {
            return redirect()->away($bill->bill_file_url);
        }
        
        $format   = $customer->preferred_bill_format ?? 'excel';

        $r2Path = $this->billing->getCachedFilePath($format, $billno);
        $safeBillNo = str_replace(['/', '\\'], '_', $billno);
        
        if (\Illuminate\Support\Facades\Storage::disk('r2')->exists($r2Path)) {
            $ext = $format === 'excel' ? 'xlsx' : $format;
            return \Illuminate\Support\Facades\Storage::disk('r2')->download($r2Path, "bill_{$safeBillNo}.{$ext}");
        }

        preg_match('/(\d+)$/', $billno, $matches);
        $numericId = (int) ($matches[1] ?? $billno);

        $items = $this->billing->getBillDetails($numericId);

        if (empty($items)) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $billNoStr    = $items[0]['BILLNO'] ?? (string) $billno;
        $billDate     = $items[0]['BILLDATE'] ?? now()->format('Y-m-d');
        $customerName = $customer->user->name ?? 'Customer';

        switch ($format) {
            case 'pdf':
                $path     = $this->billing->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename = "bill_{$safeBillNo}.pdf";
                $mime     = 'application/pdf';
                break;

            case 'csv':
                $path     = $this->billing->generateCsv($items, $billNoStr);
                $filename = "bill_{$safeBillNo}.csv";
                $mime     = 'text/csv';
                break;

            default: // excel
                $path     = $this->billing->generateExcel($items, $billNoStr, $billDate);
                $filename = "bill_{$safeBillNo}.xlsx";
                $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return \Illuminate\Support\Facades\Storage::disk('r2')->download($path, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}
