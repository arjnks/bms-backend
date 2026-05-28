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
        if (!$customer->external_cucode) {
            abort(422, 'Your account is not linked to the billing system yet. Please contact admin.');
        }
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
    public function show(Request $request, int $billno)
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
     * Download bill in customer's preferred format.
     * GET /customer/external-bills/{billno}/download
     */
    public function download(Request $request, int $billno)
    {
        $customer = $this->getCustomer($request);
        $format   = $customer->preferred_bill_format ?? 'excel';

        $items = $this->billing->getBillDetails($billno);

        if (empty($items)) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $billNoStr    = $items[0]['BILLNO'] ?? (string) $billno;
        $billDate     = $items[0]['BILLDATE'] ?? now()->format('Y-m-d');
        $customerName = $customer->user->name ?? 'Customer';

        switch ($format) {
            case 'pdf':
                $path     = $this->billing->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename = "bill_{$billno}.pdf";
                $mime     = 'application/pdf';
                break;

            case 'csv':
                $path     = $this->billing->generateCsv($items, $billno);
                $filename = "bill_{$billno}.csv";
                $mime     = 'text/csv';
                break;

            default: // excel
                $path     = $this->billing->generateExcel($items, $billNoStr, $billDate);
                $filename = "bill_{$billno}.xlsx";
                $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }

        return response()->download($path, $filename, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ])->deleteFileAfterSend(true);
    }
}
