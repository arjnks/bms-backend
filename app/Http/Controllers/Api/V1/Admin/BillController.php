<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\ReminderLog;
use App\Services\ExternalBillingService;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    private function isExternalUrl(?string $url): bool
    {
        return is_string($url) && preg_match('/^https?:\/\//i', $url);
    }



    public function overview()
    {
        $total_outstanding = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])->sum('grand_total');
        $bills_today = Bill::whereDate('created_at', Carbon::today())->count();
        $overdue_count = Bill::where('due_date', '<', Carbon::today())->where('payment_status', 'unpaid')->count();
        
        $total_bills = Bill::count();
        $paid_bills = Bill::where('payment_status', 'paid')->count();
        $collection_rate = $total_bills > 0 ? round(($paid_bills / $total_bills) * 100, 2) : 0;
        
        $reminders_this_month = ReminderLog::whereMonth('sent_at', Carbon::now()->month)
            ->whereYear('sent_at', Carbon::now()->year)
            ->count();

        return response()->json([
            'total_outstanding' => $total_outstanding,
            'bills_today' => $bills_today,
            'overdue_count' => $overdue_count,
            'collection_rate' => $collection_rate,
            'reminders_this_month' => $reminders_this_month,
        ]);
    }

    public function index(Request $request)
    {
        $query = Bill::with('customer.user:id,name');

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Allow filtering by payment_status (used by PaymentVerifications page)
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('is_overdue') && $request->is_overdue) {
            $query->where('due_date', '<', Carbon::today())->whereIn('payment_status', ['unpaid', 'proof_rejected']);
        }

        $bills = $query->orderBy('created_at', 'desc')->paginate(15);

        $bills->getCollection()->transform(function ($bill) {
            return [
                'id'                    => $bill->id,
                'customer_name'         => $bill->customer?->user?->name ?? '—',
                'invoice_no'            => $bill->invoice_no,
                'bill_date'             => $bill->bill_date?->format('d M Y'),
                'due_date'              => $bill->due_date?->format('d M Y'),
                'grand_total'           => $bill->grand_total,
                'status'                => $bill->status,
                'payment_status'        => $bill->payment_status,
                'bill_file_type'        => $bill->bill_file_type,
                // Payment proof fields
                'payment_method'        => $bill->payment_method,
                'utr_number'            => $bill->utr_number,
                'proof_screenshot'      => $bill->proof_screenshot,
                'payment_submitted_at'  => $bill->payment_submitted_at,
                'payment_verified_at'   => $bill->payment_verified_at,
                'rejection_reason'      => $bill->rejection_reason,
                'updated_at'            => $bill->updated_at,
            ];
        });

        return response()->json($bills);
    }

    public function show($id)
    {
        $bill = Bill::with(['customer.user'])->findOrFail($id);

        // Attempt to eager-load line items — gracefully handle missing table
        $lineItems = collect();
        try {
            $lineItems = $bill->lineItems;
        } catch (\Illuminate\Database\QueryException $e) {
            // bill_line_items table may not exist yet — migration pending
            Log::warning('bill_line_items table missing, skipping line items', ['error' => $e->getMessage()]);
        }

        if ($lineItems->isEmpty() && $bill->customer && $bill->customer->external_cucode) {
            $billNo = (string) $bill->invoice_no;

            if ($billNo !== '') {
                try {
                    $billingService = app(ExternalBillingService::class);
                    $items = $billingService->getBillDetails($billNo);
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
                        $lineItems = $bill->lineItems; // reload after saving
                    }
                } catch (\Throwable $e) {
                    Log::warning('ERP bill details unavailable on admin bill show', [
                        'bill_id' => $bill->id,
                        'invoice_no' => $bill->invoice_no,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $proofUrl = null;
        if ($bill->proof_screenshot) {
            try { $proofUrl = Storage::disk('public')->url($bill->proof_screenshot); } catch (\Exception $e) {}
        }

        return response()->json([
            'id'                   => $bill->id,
            'invoice_no'           => $bill->invoice_no,
            'customer_id'          => $bill->customer?->id,
            'customer_name'        => $bill->customer?->user?->name ?? '—',
            'customer_code'        => $bill->customer?->customer_code ?? null,
            'bill_date'            => $bill->bill_date?->format('d M Y'),
            'due_date'             => $bill->due_date?->format('d M Y'),
            'subtotal'             => $bill->subtotal,
            'gst_total'            => $bill->gst_total,
            'grand_total'          => $bill->grand_total,
            'status'               => $bill->status,
            'payment_status'       => $bill->payment_status,
            'payment_method'       => $bill->payment_method,
            'utr_number'           => $bill->utr_number,
            'proof_screenshot'     => $bill->proof_screenshot,
            'proof_url'            => $proofUrl,
            'payment_submitted_at' => $bill->payment_submitted_at,
            'payment_verified_at'  => $bill->payment_verified_at,
            'rejection_reason'     => $bill->rejection_reason,
            'bill_file_type'       => $bill->bill_file_type,
            'line_items'           => $lineItems->values(),
        ]);
    }

    public function download(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $requestedFormat = $request->query('format');

        if ($this->isExternalUrl($bill->bill_file_url) && !$requestedFormat) {
            return response()->json(['download_url' => $bill->bill_file_url]);
        }

        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\Cache::put("bill_token_{$token}", [
            'id' => $bill->id,
            'customer_id' => null, // Admin doesn't need customer verification
            'format' => $requestedFormat,
        ], now()->addMinutes(30));

        $url = "/api/v1/customer/bills/stream-token/{$token}";
        return response()->json(['download_url' => $url]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_no' => 'required|string|unique:bills,invoice_no',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'subtotal' => 'required|numeric|min:0',
            'gst_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'bill_file_type' => ['required', Rule::in(['csv', 'excel', 'pdf'])],
            'bill_file' => 'nullable|file',
            'line_items' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $bill = Bill::create([
                'customer_id' => $validated['customer_id'],
                'invoice_no' => $validated['invoice_no'],
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $validated['subtotal'],
                'gst_total' => $validated['gst_total'],
                'grand_total' => $validated['grand_total'],
                'status' => 'unpaid',
                'payment_status' => 'unpaid',
                'bill_file_type' => $validated['bill_file_type'],
                'uploaded_by' => $request->user()->id,
            ]);

            if (!empty($validated['line_items'])) {
                foreach ($validated['line_items'] as $item) {
                    $bill->lineItems()->create($item);
                }
            }

            $path = null;
            if ($request->hasFile('bill_file')) {
                $path = $request->file('bill_file')->store('bills', 'public');
            } elseif ($validated['bill_file_type'] === 'pdf') {
                $pdf = Pdf::loadView('pdf.bill', ['bill' => $bill]);
                $path = 'bills/' . $bill->invoice_no . '.pdf';
                Storage::disk('public')->put($path, $pdf->output());
            }

            $bill->update(['bill_file_url' => $path]);

            return response()->json($bill->load('customer.user'), 201);
        });
    }

    public function markPaid(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);
        
        $bill->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_verified_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Bill marked as paid', 'bill' => $bill]);
    }

    public function revertPayment(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);
        
        $isPastDue = $bill->due_date && Carbon::parse($bill->due_date)->isPast();

        $bill->update([
            'status' => $isPastDue ? 'overdue' : 'unpaid',
            'payment_status' => 'unpaid',
            'payment_verified_at' => null,
            'payment_submitted_at' => null,
            'proof_screenshot' => null,
            'utr_number' => null,
            'payment_method' => null,
        ]);

        return response()->json(['message' => 'Bill reverted to unpaid', 'bill' => $bill]);
    }

    public function verifyPayment(Request $request, $id)
    {
        $bill = Bill::with('customer.user')->findOrFail($id);
        
        $bill->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_verified_at' => Carbon::now(),
        ]);

        if ($bill->customer->user->phone) {
            $msg = "Hi {$bill->customer->user->name}, your payment of ₹{$bill->grand_total} for {$bill->invoice_no} has been confirmed. Thank you! — Leo Group";
            $this->whatsapp->send($bill->customer->user->phone, $msg);
        }

        return response()->json(['message' => 'Payment verified successfully', 'bill' => $bill]);
    }

    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $bill = Bill::with('customer.user')->findOrFail($id);
        
        $bill->update([
            'payment_status' => 'proof_rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        if ($bill->customer->user->phone) {
            $msg = "Hi {$bill->customer->user->name}, your proof for {$bill->invoice_no} could not be verified. Reason: {$request->rejection_reason}. Please resubmit: " . url('/portal');
            $this->whatsapp->send($bill->customer->user->phone, $msg);
        }

        return response()->json(['message' => 'Payment proof rejected', 'bill' => $bill]);
    }
}
