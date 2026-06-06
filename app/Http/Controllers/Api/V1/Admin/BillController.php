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
        $now   = Carbon::now();
        $today = Carbon::today();

        // --- Live ERP financial figures (source of truth) ---
        $erpDashboard    = [];
        $erpOutstanding  = null;
        $erpOverdue      = null;
        $erpBillCount    = null;

        try {
            $erpUrl = rtrim(env('EXTERNAL_BILLING_URL', 'https://billing.leopharma.tech'), '/') . '/API/announcements/dashboard_data.php';

            $erpResponse = \Illuminate\Support\Facades\Http::timeout(10)
                ->withOptions([
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ])
                ->get($erpUrl);

            if ($erpResponse->successful()) {
                $erpData        = $erpResponse->json('data', []);
                $erpOutstanding = $erpData['total_outstandings'] ?? null;
                $erpOverdue     = $erpData['total_overdue']      ?? null;
                $erpBillCount   = $erpData['current_bill_count'] ?? null;
            }
        } catch (\Exception $e) {
            // Silently fallback to local DB if ERP is unreachable or log is unwritable
        }

        // --- KPI numbers (fallback to local DB if ERP is unreachable) ---
        $total_outstanding = $erpOutstanding ?? Bill::where('is_settled', false)
            ->sum(DB::raw('grand_total - IFNULL(amount_received, 0)'));

        $overdue_amount = $erpOverdue ?? Bill::where('due_date', '<', $today)
            ->where('is_settled', false)
            ->sum(DB::raw('grand_total - IFNULL(amount_received, 0)'));

        $bills_today   = $erpBillCount ?? Bill::whereDate('bill_date', $today)->count();
        $overdue_count = Bill::where('due_date', '<', $today)->where('is_settled', false)->count();

        $total_bills     = Bill::count();
        $paid_bills      = Bill::where('payment_status', 'paid')->count();
        $collection_rate = $total_bills > 0 ? round(($paid_bills / $total_bills) * 100, 2) : 0;

        $reminders_this_month = ReminderLog::whereMonth('sent_at', $now->month)
            ->whereYear('sent_at', $now->year)
            ->count();

        // --- Recent Activity: last 10 bills updated ---
        $recent_bills = Bill::with('customer.user:id,name')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($b) => [
                'id'              => $b->id,
                'invoice_no'      => $b->invoice_no,
                'customer_name'   => $b->customer?->user?->name ?? 'Unknown',
                'customer_id'     => $b->customer_id,
                'payment_status'  => $b->payment_status,
                'grand_total'     => $b->grand_total,
                'amount_received' => $b->amount_received,
                'due_amount'      => max(0, $b->grand_total - $b->amount_received),
                'bill_date'       => $b->bill_date?->format('d M Y'),
                'updated_at'      => $b->updated_at?->diffForHumans(),
            ]);

        // --- Top Overdue: customers with highest total outstanding due ---
        $top_overdue = Bill::select(
                'customer_id',
                DB::raw('SUM(grand_total - IFNULL(amount_received,0)) as total_due'),
                DB::raw('COUNT(*) as bill_count')
            )
            ->where('is_settled', false)
            ->where('due_date', '<', $today)
            ->groupBy('customer_id')
            ->orderByDesc('total_due')
            ->limit(5)
            ->with('customer.user:id,name')
            ->get()
            ->map(fn ($row) => [
                'customer_id'   => $row->customer_id,
                'customer_name' => $row->customer?->user?->name ?? 'Unknown',
                'total_due'     => round($row->total_due, 2),
                'bill_count'    => $row->bill_count,
            ]);

        // --- Monthly Collections: last 12 months ---
        $chart_collections = collect(range(11, 0))->map(function ($i) use ($now) {
            $month = $now->copy()->subMonths($i);
            $collected = Bill::whereYear('bill_date', $month->year)
                ->whereMonth('bill_date', $month->month)
                ->sum('amount_received');
            return [
                'month'     => $month->format('M'),
                'year'      => $month->year,
                'collected' => round($collected, 2),
            ];
        })->values();

        // --- Payment Status donut ---
        $unpaid_count = Bill::whereIn('payment_status', ['unpaid', 'proof_rejected'])
            ->where('is_settled', false)->count();
        $chart_payment_status = [
            'paid'     => $paid_bills,
            'due_soon' => Bill::where('is_settled', false)
                ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
                ->count(),
            'overdue'  => $overdue_count,
        ];

        return response()->json([
            'total_outstanding'     => $total_outstanding,
            'overdue_amount'        => $overdue_amount,
            'bills_today'           => $bills_today,
            'overdue_count'         => $overdue_count,
            'collection_rate'       => $collection_rate,
            'reminders_this_month'  => $reminders_this_month,
            'total_unpaid'          => $unpaid_count + $overdue_count,
            'dues_this_month'       => $total_outstanding,
            'dues_this_month_count' => $unpaid_count + $overdue_count,
            'recent_bills'          => $recent_bills,
            'top_overdue'           => $top_overdue,
            'chart_collections'     => $chart_collections,
            'chart_payment_status'  => $chart_payment_status,
            'erp_live'              => $erpOutstanding !== null, // flag for frontend: true = ERP data, false = local fallback
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

        if ($request->filled('from_date')) {
            $query->whereDate('bill_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('bill_date', '<=', $request->to_date);
        }

        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'highest_overdue':
                    $query->orderByRaw('(grand_total - amount_received) DESC');
                    break;
                case 'lowest_overdue':
                    $query->orderByRaw('(grand_total - amount_received) ASC');
                    break;
                case 'oldest':
                    $query->orderBy('bill_date', 'asc');
                    break;
                case 'newest':
                    $query->orderBy('bill_date', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bills = $query->paginate(15);

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
                            } catch (\Exception $e) {
                                Log::error('Failed to create line item dynamically', ['bill_id' => $bill->id, 'error' => $e->getMessage(), 'item' => $item]);
                            }
                        }
                        $lineItems = $bill->lineItems()->get(); // reload from DB after saving
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
            try {
                // proof_screenshot is an R2 key — generate a 60-minute signed URL
                $proofUrl = Storage::disk('r2')->temporaryUrl($bill->proof_screenshot, now()->addMinutes(60));
            } catch (\Exception $e) {
                // fallback: return the raw path so the admin can at least see what's stored
                $proofUrl = $bill->proof_screenshot;
            }
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

            $r2Key = null;
            if ($request->hasFile('bill_file')) {
                // Store the R2 key, not a signed URL — signed URLs are generated at download time
                $r2Key  = 'bills/uploads/' . $bill->invoice_no . '_' . $request->file('bill_file')->getClientOriginalName();
                $r2Key  = preg_replace('/[^A-Za-z0-9._\-\/]/', '_', $r2Key);
                Storage::disk('r2')->put($r2Key, file_get_contents($request->file('bill_file')->getRealPath()), [
                    'ContentType' => $request->file('bill_file')->getMimeType(),
                ]);
            } elseif ($validated['bill_file_type'] === 'pdf') {
                $pdf   = Pdf::loadView('pdf.bill', ['bill' => $bill])->setPaper('a4', 'landscape');
                $r2Key = 'bills/pdf/' . preg_replace('/[^A-Za-z0-9._\-]/', '_', $bill->invoice_no) . '.pdf';
                Storage::disk('r2')->put($r2Key, $pdf->output(), ['ContentType' => 'application/pdf']);
            }

            $bill->update(['bill_file_url' => $r2Key]);

            $bill->load('customer.user');
            if ($bill->customer && $bill->customer->user && $bill->customer->user->phone) {
                app(\App\Services\WhatsAppService::class)->sendTemplate($bill->customer->user->phone, 'new_bill_uploaded_v1', [
                    $bill->customer->user->name,
                    $bill->invoice_no,
                    $bill->grand_total
                ]);
            }

            return response()->json($bill, 201);
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
            $this->whatsapp->sendTemplate($bill->customer->user->phone, 'payment_verified_v1', [
                $bill->customer->user->name,
                $bill->invoice_no
            ]);
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
            $this->whatsapp->sendTemplate($bill->customer->user->phone, 'payment_rejected_v1', [
                $bill->customer->user->name,
                $bill->invoice_no,
                $request->rejection_reason
            ]);
        }

        return response()->json(['message' => 'Payment proof rejected', 'bill' => $bill]);
    }
}
