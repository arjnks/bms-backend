<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Models\ReminderLog;
use App\Services\WhatsAppService;
use App\Services\ExternalBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CustomerController extends Controller
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function index(Request $request)
    {
        $customers = Customer::with('user:id,name,email,phone')
            ->withSum([
                'bills as outstanding_amount' => function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'proof_rejected']);
                }
            ], 'grand_total')
            ->withMin([
                'bills as nearest_due_date' => function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'proof_rejected']);
                }
            ], 'due_date')
            ->withMax('reminderLogs as last_reminder_sent', 'sent_at')
            ->get();

        $customers = $customers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'external_cucode' => $customer->external_cucode,
                'name' => $customer->user->name ?? 'Unknown',
                'email' => $customer->user->email ?? 'N/A',
                'phone' => $customer->user->phone ?? 'N/A',
                'preferred_bill_format' => $customer->preferred_bill_format,
                'outstanding_amount' => $customer->outstanding_amount ?? 0,
                'nearest_due_date' => $customer->nearest_due_date,
                'last_reminder_sent' => $customer->last_reminder_sent,
            ];
        });

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:8',
            'customer_code' => 'required|string|unique:customers,customer_code',
            'gstin' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'preferred_bill_format' => ['required', Rule::in(['csv', 'excel', 'pdf'])],
            'salesperson_id' => 'nullable|exists:users,id',
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => 'customer',
                'status' => 'active',
            ]);

            return Customer::create([
                'user_id' => $user->id,
                'customer_code' => $validated['customer_code'],
                'gstin' => $validated['gstin'] ?? null,
                'credit_limit' => $validated['credit_limit'] ?? 0,
                'preferred_bill_format' => $validated['preferred_bill_format'],
                'salesperson_id' => $validated['salesperson_id'] ?? null,
            ]);
        });

        return response()->json($customer->load('user'), 201);
    }

    public function show($id)
    {
        $customer = Customer::with(['user', 'bills', 'reminderLogs'])->findOrFail($id);
        return response()->json($customer);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'nullable', 'string', Rule::unique('users')->ignore($customer->user_id)],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($customer->user_id)],
            'phone' => 'nullable|string',
            'gstin' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'preferred_bill_format' => ['sometimes', Rule::in(['csv', 'excel', 'pdf'])],
            'salesperson_id' => 'nullable|exists:users,id',
            'external_cucode' => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($validated, $customer) {
            if (isset($validated['name']) || array_key_exists('username', $validated) || array_key_exists('email', $validated) || array_key_exists('phone', $validated)) {
                $userData = [];
                if (isset($validated['name']))
                    $userData['name'] = $validated['name'];
                if (array_key_exists('username', $validated))
                    $userData['username'] = $validated['username'];
                if (array_key_exists('email', $validated))
                    $userData['email'] = $validated['email'];
                if (array_key_exists('phone', $validated))
                    $userData['phone'] = $validated['phone'];
                $customer->user->update($userData);
            }

            $customerData = [];
            if (array_key_exists('gstin', $validated))
                $customerData['gstin'] = $validated['gstin'];
            if (array_key_exists('credit_limit', $validated))
                $customerData['credit_limit'] = $validated['credit_limit'];
            if (isset($validated['preferred_bill_format']))
                $customerData['preferred_bill_format'] = $validated['preferred_bill_format'];
            if (array_key_exists('salesperson_id', $validated))
                $customerData['salesperson_id'] = $validated['salesperson_id'];
            if (array_key_exists('external_cucode', $validated))
                $customerData['external_cucode'] = $validated['external_cucode'];

            if (!empty($customerData)) {
                $customer->update($customerData);
            }
        });

        return response()->json($customer->fresh('user'));
    }

    public function destroy($id)
    {
        $customer = Customer::with('user')->findOrFail($id);
        $name = $customer->user?->name ?? 'Customer';

        DB::transaction(function () use ($customer) {
            // Delete related data first to respect FK constraints
            $customer->bills()->delete();
            $customer->reminderLogs()->delete();
            $customer->delete();
            $customer->user?->tokens()->delete();
            $customer->user?->delete();
        });

        return response()->json(['message' => "{$name} has been removed from BMS."]);
    }

    public function remind(Request $request, $id)
    {
        $customer = Customer::with('user', 'bills')->findOrFail($id);
        $overdueBills = $customer->bills->whereIn('payment_status', ['unpaid', 'proof_rejected']);

        if ($overdueBills->isEmpty()) {
            return response()->json(['message' => 'No outstanding bills to remind about.'], 400);
        }

        $total = $overdueBills->sum('grand_total');

        if ($customer->user->phone) {
            $msg = "Hi {$customer->user->name}, your payment of ₹{$total} is overdue. Please log in to your Leo Group portal to view and pay your bills.";
            $this->whatsapp->send($customer->user->phone, $msg);

            ReminderLog::create([
                'customer_id' => $customer->id,
                'channel' => 'whatsapp',
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        }

        return response()->json(['message' => 'Reminder dispatched successfully']);
    }

    public function bulkRemind(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id'
        ]);

        $customers = Customer::with('user', 'bills')->whereIn('id', $request->customer_ids)->get();
        $count = 0;

        foreach ($customers as $customer) {
            $overdueBills = $customer->bills->whereIn('payment_status', ['unpaid', 'proof_rejected']);

            if ($overdueBills->isEmpty() || !$customer->user->phone) {
                continue;
            }

            $total = $overdueBills->sum('grand_total');
            $msg = "Hi {$customer->user->name}, you have an outstanding balance of ₹{$total}. Please log in to your Leo Group portal to view and pay your bills.";

            $this->whatsapp->send($customer->user->phone, $msg);

            ReminderLog::create([
                'customer_id' => $customer->id,
                'channel' => 'whatsapp',
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            $count++;
        }

        return response()->json(['message' => "Bulk reminders dispatched to {$count} customers"]);
    }

    public function externalBills(Request $request, $id, ExternalBillingService $billing)
    {
        $customer = Customer::findOrFail($id);
        if (!$customer->external_cucode) {
            return response()->json(['message' => 'Customer not linked to ERP billing system.'], 400);
        }

        $request->validate([
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);

        $bills = $billing->getBills($customer->external_cucode, $request->from_date, $request->to_date);
        usort($bills, fn($a, $b) => strcmp($b['DATE'] ?? '', $a['DATE'] ?? ''));

        return response()->json([
            'status' => 'success',
            'count' => count($bills),
            'data' => $bills,
        ]);
    }

    public function externalBillDetails(Request $request, $id, $billno, ExternalBillingService $billing)
    {
        $customer = Customer::findOrFail($id);
        if (!$customer->external_cucode) {
            return response()->json(['message' => 'Customer not linked to ERP billing system.'], 400);
        }

        $items = $billing->getBillDetails($billno);

        if (empty($items)) {
            return response()->json(['status' => 'error', 'message' => 'Bill not found or no items.'], 404);
        }

        // Verify that this bill actually belongs to this customer
        if (isset($items[0]['cucode']) && $items[0]['cucode'] !== $customer->external_cucode) {
            return response()->json(['status' => 'error', 'message' => 'Bill does not belong to this customer.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'count' => count($items),
            'data' => $items,
            'summary' => [
                'bill_no' => $items[0]['BILLNO'] ?? '',
                'bill_date' => $items[0]['BILLDATE'] ?? '',
                'net_amount' => $items[0]['NETAMOUNT'] ?? 0,
            ],
        ]);
    }

    public function downloadExternalBill(Request $request, $id, $billno, ExternalBillingService $billing)
    {
        $customer = Customer::with('user')->findOrFail($id);
        if (!$customer->external_cucode) {
            return response()->json(['message' => 'Customer not linked to ERP billing system.'], 400);
        }

        $format = $request->query('format') ?? $customer->preferred_bill_format ?? 'pdf';

        $safeBillno = str_replace(['/', '\\'], '_', $billno);
        $r2Path = $billing->getCachedFilePath($format, $billno);

        if (\Illuminate\Support\Facades\Storage::disk('r2')->exists($r2Path)) {
            $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($r2Path, now()->addMinutes(15));
            return response()->json(['download_url' => $url]);
        }

        preg_match('/(\d+)$/', $billno, $matches);
        $numericId = (int) ($matches[1] ?? $billno);

        $items = $billing->getBillDetails($numericId);

        if (empty($items)) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        if (isset($items[0]['cucode']) && $items[0]['cucode'] !== $customer->external_cucode) {
            return response()->json(['message' => 'Bill does not belong to this customer.'], 403);
        }

        $billNoStr = $items[0]['BILLNO'] ?? (string) $billno;
        $billDate = $items[0]['BILLDATE'] ?? now()->format('Y-m-d');
        $customerName = $customer->user->name ?? 'Customer';

        switch ($format) {
            case 'pdf':
                $path = $billing->generatePdf($items, $billNoStr, $billDate, $customerName);
                break;
            case 'csv':
                $path = $billing->generateCsv($items, $billNoStr, $billDate);
                break;
            default: // excel
                $path = $billing->generateExcel($items, $billNoStr, $billDate);
                break;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(15));
        return response()->json(['download_url' => $url]);
    }
}
