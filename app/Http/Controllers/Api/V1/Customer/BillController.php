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
            $lineItems = $bill->lineItems;
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::warning('bill_line_items table missing on customer show', ['error' => $e->getMessage()]);
        }

        $billArray = $bill->toArray();
        $billArray['line_items'] = $lineItems;

        return response()->json($billArray);
    }

    public function download(Request $request, $id)
    {
        $customerId = $this->getCustomerId($request);

        $bill = Bill::where('customer_id', $customerId)->findOrFail($id);

        // If we pre-generated and uploaded to cloud (S3/R2)
        if ($bill->bill_file_url) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }

        // Fallback: Generate a signed URL to stream it live from ERP
        $url = URL::temporarySignedRoute('bills.download.stream', now()->addMinutes(30), ['id' => $bill->id]);
        return response()->json(['download_url' => $url]);
    }

    public function stream(Request $request, $id, ExternalBillingService $billingService)
    {
        // No customerId check because it's a signed route, but we must ensure it exists.
        $bill = Bill::with('customer.user')->findOrFail($id);

        // Extract the raw numeric billno from a string like "LPH/2627/96609"
        preg_match('/(\d+)$/', $bill->invoice_no, $matches);
        $numericId = (int) ($matches[1] ?? $bill->invoice_no);

        $items = $billingService->getBillDetails($numericId);

        if (empty($items)) {
            return response()->json(['message' => 'No file associated and ERP fetch failed.'], 404);
        }

        $customerName = $bill->customer->user->name ?? 'Customer';
        $format = $bill->customer->preferred_bill_format ?? 'pdf';
        
        $billNoStr = $items[0]['BILLNO'] ?? (string) $bill->invoice_no;
        $billDate = $items[0]['BILLDATE'] ?? ($bill->bill_date ? $bill->bill_date->format('Y-m-d') : now()->format('Y-m-d'));

        switch ($format) {
            case 'pdf':
                $path     = $billingService->generatePdf($items, $billNoStr, $billDate, $customerName);
                $filename = "bill_{$billNoStr}.pdf";
                $mime     = 'application/pdf';
                break;

            case 'csv':
                $path     = $billingService->generateCsv($items, $bill->invoice_no);
                $filename = "bill_{$billNoStr}.csv";
                $mime     = 'text/csv';
                break;

            default: // excel
                $path     = $billingService->generateExcel($items, $billNoStr, $billDate);
                $filename = "bill_{$billNoStr}.xlsx";
                $mime     = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }

        return response()->download($path, $filename, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ])->deleteFileAfterSend(true);
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
