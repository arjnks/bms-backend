<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $bill = Bill::with('lineItems')
            ->where('customer_id', $customerId)
            ->findOrFail($id);

        return response()->json($bill);
    }

    public function download(Request $request, $id)
    {
        $customerId = $this->getCustomerId($request);

        $bill = Bill::where('customer_id', $customerId)->findOrFail($id);

        if (!$bill->bill_file_url) {
            return response()->json(['message' => 'No file associated with this bill.'], 404);
        }

        // Generate a public URL to the locally-stored file
        $url = Storage::disk('public')->url($bill->bill_file_url);

        return response()->json(['download_url' => $url]);
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
