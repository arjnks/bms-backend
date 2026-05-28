<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\ExternalBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ErpImportController extends Controller
{
    protected ExternalBillingService $billing;

    public function __construct(ExternalBillingService $billing)
    {
        $this->billing = $billing;
    }

    /**
     * List all customers from the external ERP API,
     * annotated with whether they already exist in BMS.
     *
     * GET /admin/erp/customers
     */
    public function index()
    {
        $erpCustomers = $this->billing->getCustomers();

        if (empty($erpCustomers)) {
            return response()->json(['status' => 'error', 'message' => 'Could not reach ERP API.'], 502);
        }

        // Build a quick lookup: email → BMS customer
        $emails = collect($erpCustomers)->pluck('EMAIL')->filter()->map(fn($e) => strtolower($e))->all();

        $existing = User::whereIn(DB::raw('LOWER(email)'), $emails)
            ->where('role', 'customer')
            ->with('customer:user_id,customer_code,external_cucode,preferred_bill_format')
            ->get()
            ->keyBy(fn($u) => strtolower($u->email));

        $result = collect($erpCustomers)->map(function ($c) use ($existing) {
            $email  = strtolower($c['EMAIL'] ?? '');
            $inBms  = $existing->has($email);
            $bmsUser = $inBms ? $existing[$email] : null;

            return [
                'nameplace'      => $c['NAMEPLACE'] ?? '',
                'routecode'      => $c['ROUTECODE'] ?? '',
                'email'          => $c['EMAIL'] ?? '',
                'in_bms'         => $inBms,
                'bms_name'       => $bmsUser?->name,
                'customer_code'  => $bmsUser?->customer?->customer_code,
                'external_cucode'=> $bmsUser?->customer?->external_cucode,
                'preferred_bill_format' => $bmsUser?->customer?->preferred_bill_format ?? 'excel',
            ];
        });

        return response()->json([
            'status' => 'success',
            'count'  => $result->count(),
            'data'   => $result->values(),
        ]);
    }

    /**
     * Bulk-import (create or update) a batch of ERP customers into BMS.
     *
     * POST /admin/erp/import
     * Body: { customers: [{nameplace, email, cucode, preferred_bill_format}] }
     */
    public function import(Request $request)
    {
        $request->validate([
            'customers'                         => 'required|array|min:1|max:200',
            'customers.*.nameplace'             => 'required|string',
            'customers.*.email'                 => 'required|email',
            'customers.*.cucode'                => 'nullable|string|max:50',
            'customers.*.preferred_bill_format' => 'nullable|in:csv,excel,pdf',
        ]);

        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($request->customers as $item) {
            try {
                $email     = strtolower($item['email']);
                $nameplace = $item['nameplace'];
                $cucode    = $item['cucode'] ?? null;
                $format    = $item['preferred_bill_format'] ?? 'excel';

                DB::transaction(function () use ($email, $nameplace, $cucode, $format, &$created, &$updated) {
                    $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

                    if ($user) {
                        // Update existing user's customer record
                        $user->update(['name' => $nameplace]);

                        if ($user->customer) {
                            $user->customer->update([
                                'external_cucode'       => $cucode,
                                'preferred_bill_format' => $format,
                            ]);
                        } else {
                            Customer::create([
                                'user_id'               => $user->id,
                                'customer_code'         => 'ERP-' . strtoupper(Str::random(6)),
                                'external_cucode'       => $cucode,
                                'preferred_bill_format' => $format,
                            ]);
                        }
                        $updated++;
                    } else {
                        // Create new user + customer
                        $newUser = User::create([
                            'name'     => $nameplace,
                            'email'    => $email,
                            'username' => Str::slug($nameplace) . '-' . Str::random(4),
                            'password' => Hash::make(Str::random(16)), // admin will set proper password later
                            'role'     => 'customer',
                            'status'   => 'active',
                        ]);

                        Customer::create([
                            'user_id'               => $newUser->id,
                            'customer_code'         => 'ERP-' . strtoupper(Str::random(6)),
                            'external_cucode'       => $cucode,
                            'preferred_bill_format' => $format,
                        ]);
                        $created++;
                    }
                });
            } catch (\Exception $e) {
                $errors[] = ['email' => $item['email'], 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'status'  => 'success',
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }

    /**
     * Set or update the cucode for a single customer by email.
     *
     * PATCH /admin/erp/cucode
     * Body: { email, cucode }
     */
    public function setCucode(Request $request)
    {
        $request->validate([
            'email'  => 'required|email',
            'cucode' => 'nullable|string|max:50',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

        if (!$user || !$user->customer) {
            return response()->json(['message' => 'Customer not found in BMS.'], 404);
        }

        $user->customer->update(['external_cucode' => $request->cucode]);

        return response()->json(['status' => 'success', 'message' => 'cucode updated.']);
    }
}
