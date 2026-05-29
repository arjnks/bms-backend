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

class SyncController extends Controller
{
    protected ExternalBillingService $billing;

    public function __construct(ExternalBillingService $billing)
    {
        $this->billing = $billing;
    }

    /**
     * Sync ALL customers from the external billing API into BMS.
     * POST /admin/sync/customers
     *
     * Zero customers are skipped regardless of missing email/phone.
     * Match strategy:
     *   A) Has real email  → match/create by email
     *   B) No email, has code → match by external_cucode; create if new
     *   C) No email, no code  → create with guaranteed-unique placeholder email
     */
    public function syncCustomers(Request $request)
    {
        set_time_limit(300);

        $erpCustomers = $this->billing->getCustomers();

        if (empty($erpCustomers)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not reach the billing system. Check if the billing server is online.',
            ], 502);
        }

        $now     = now();
        $created = 0;
        $updated = 0;

        // Pre-load existing BMS data for fast in-memory lookups
        $usersByEmail = User::where('role', 'customer')
            ->select(['id', 'email', 'name', 'phone'])
            ->get()
            ->keyBy(fn($u) => strtolower($u->email));

        $customersByCode = Customer::whereNotNull('external_cucode')
            ->select(['id', 'user_id', 'external_cucode'])
            ->get()
            ->keyBy('external_cucode');

        // Track placeholder emails used this run to avoid collisions
        $usedPlaceholders = $usersByEmail->keys()->flip()->all();

        $userRowsToInsert = [];

        foreach ($erpCustomers as $idx => $c) {
            $rawEmail = strtolower(trim($c['EMAIL'] ?? ''));
            $code     = trim($c['code'] ?? '');
            $name     = trim($c['NAMEPLACE'] ?? 'Unknown Customer');
            $phone    = trim($c['WHATSAPPNO'] ?? '') ?: null;
            $hasReal  = ($rawEmail && filter_var($rawEmail, FILTER_VALIDATE_EMAIL));

            // ── Strategy A: has a real email ─────────────────────────────────
            if ($hasReal) {
                $existingUser = $usersByEmail[$rawEmail] ?? null;

                if ($existingUser) {
                    $changed = false;
                    if ($existingUser->name !== $name) { $existingUser->name = $name; $changed = true; }
                    if ($phone && $existingUser->phone !== $phone) { $existingUser->phone = $phone; $changed = true; }
                    if ($changed) { $existingUser->save(); $updated++; }

                    // Sync cucode on existing Customer record
                    $cust = $customersByCode[$code] ?? Customer::where('user_id', $existingUser->id)->first();
                    if ($cust && $code && $cust->external_cucode !== $code) {
                        $cust->external_cucode = $code;
                        $cust->save();
                    }
                } else {
                    $userRowsToInsert[] = [
                        'email' => $rawEmail,
                        'name'  => $name,
                        'phone' => $phone,
                        'code'  => $code,
                    ];
                    $usedPlaceholders[$rawEmail] = true;
                }
                continue;
            }

            // ── Strategy B: no email, has code — match by cucode ─────────────
            if ($code) {
                $existingCust = $customersByCode[$code] ?? null;

                if ($existingCust) {
                    $user = User::find($existingCust->user_id);
                    if ($user) {
                        $changed = false;
                        if ($user->name !== $name) { $user->name = $name; $changed = true; }
                        if ($phone && $user->phone !== $phone) { $user->phone = $phone; $changed = true; }
                        if ($changed) { $user->save(); $updated++; }
                    }
                    continue;
                }
            }

            // ── Strategy C: new customer with no email / new code ─────────────
            $slug = $code ? preg_replace('/[^a-z0-9]/', '', strtolower($code)) : '';
            $candidate = ($slug ?: 'cust' . $idx) . '@noemail.leo';
            // Guarantee uniqueness even if slug collides
            if (isset($usedPlaceholders[$candidate])) {
                $candidate = ($slug ?: 'cust') . $idx . '@noemail.leo';
            }

            $usedPlaceholders[$candidate] = true;
            $userRowsToInsert[] = [
                'email' => $candidate,
                'name'  => $name,
                'phone' => $phone,
                'code'  => $code,
            ];
        }

        // ── Bulk insert new users then create Customer records ────────────────
        if (!empty($userRowsToInsert)) {
            $codeMap = [];
            $dbRows  = [];

            foreach ($userRowsToInsert as $r) {
                $codeMap[$r['email']] = $r['code'];
                $dbRows[] = [
                    'name'       => $r['name'],
                    'email'      => $r['email'],
                    'phone'      => $r['phone'],
                    'username'   => 'user-' . substr(md5($r['email']), 0, 8),
                    'password'   => Hash::make(Str::random(16)),
                    'role'       => 'customer',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($dbRows, 200) as $chunk) {
                DB::table('users')->insertOrIgnore($chunk);
            }

            // Fetch the freshly inserted users and create Customer records
            $newEmails = array_keys($codeMap);
            $newUsers  = User::whereIn(DB::raw('LOWER(email)'), $newEmails)
                ->where('role', 'customer')
                ->doesntHave('customer')
                ->select('id', 'email')
                ->get();

            if ($newUsers->isNotEmpty()) {
                $custRows = $newUsers->map(fn($u) => [
                    'user_id'               => $u->id,
                    'customer_code'         => 'ERP-' . strtoupper(Str::random(6)),
                    'external_cucode'       => $codeMap[strtolower($u->email)] ?: null,
                    'preferred_bill_format' => 'excel',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ])->all();

                foreach (array_chunk($custRows, 200) as $chunk) {
                    DB::table('customers')->insertOrIgnore($chunk);
                }

                $created = $newUsers->count();
            }
        }

        $totalInBms = Customer::count();

        return response()->json([
            'status'    => 'success',
            'erp_total' => count($erpCustomers),
            'bms_total' => $totalInBms,
            'created'   => $created,
            'updated'   => $updated,
            'message'   => "Sync complete. {$created} new, {$updated} updated. Total in BMS: {$totalInBms}.",
        ]);
    }


    /**
     * Get sync status — how many BMS customers vs external API customers.
     * GET /admin/sync/status
     */
    public function status()
    {
        $bmsCount    = User::where('role', 'customer')->count();
        $linkedCount = Customer::whereNotNull('external_cucode')->count();

        return response()->json([
            'bms_customers'    => $bmsCount,
            'linked_customers' => $linkedCount,
        ]);
    }
}
