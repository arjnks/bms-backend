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
     * Sync all customers from the external billing API into BMS.
     * POST /admin/sync/customers
     *
     * - Matches by email (case-insensitive)
     * - Creates account if email not found
     * - Updates name if email exists but name differs
     * - Never touches passwords of existing accounts
     */
    public function syncCustomers(Request $request)
    {
        // Increase timeout for large syncs
        set_time_limit(120);

        $erpCustomers = $this->billing->getCustomers();

        if (empty($erpCustomers)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not reach the billing system. Check if the billing server is online.',
            ], 502);
        }

        // Build a map: lowercase_email → nameplace
        $erpMap = [];
        foreach ($erpCustomers as $c) {
            $email = strtolower(trim($c['EMAIL'] ?? ''));
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erpMap[$email] = trim($c['NAMEPLACE'] ?? '');
            }
        }

        $erpEmails  = array_keys($erpMap);
        $now        = now();

        // ── Step 1: find which emails already exist in BMS ─────────────────
        $existingUsers = User::whereIn(DB::raw('LOWER(email)'), $erpEmails)
            ->where('role', 'customer')
            ->select(['id', 'email', 'name'])
            ->get()
            ->keyBy(fn($u) => strtolower($u->email));

        $existingEmails = $existingUsers->keys()->all();
        $newEmails      = array_diff($erpEmails, $existingEmails);

        // ── Step 2: bulk-insert new users ──────────────────────────────────
        $created = 0;
        if (!empty($newEmails)) {
            $userRows = [];
            foreach ($newEmails as $email) {
                $userRows[] = [
                    'name'       => $erpMap[$email],
                    'email'      => $email,
                    'username'   => 'user-' . substr(md5($email), 0, 8),
                    'password'   => Hash::make(Str::random(16)),
                    'role'       => 'customer',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert in chunks of 200 to avoid query size limits
            foreach (array_chunk($userRows, 200) as $chunk) {
                DB::table('users')->insert($chunk);
            }
            $created = count($newEmails);
        }

        // ── Step 3: create Customer records for newly inserted users ───────
        $newUsers = User::whereIn(DB::raw('LOWER(email)'), $newEmails)
            ->where('role', 'customer')
            ->doesntHave('customer')
            ->select('id')
            ->get();

        if ($newUsers->isNotEmpty()) {
            $customerRows = $newUsers->map(fn($u) => [
                'user_id'               => $u->id,
                'customer_code'         => 'ERP-' . strtoupper(Str::random(6)),
                'preferred_bill_format' => 'excel',
                'created_at'            => $now,
                'updated_at'            => $now,
            ])->all();

            foreach (array_chunk($customerRows, 200) as $chunk) {
                DB::table('customers')->insert($chunk);
            }
        }

        // ── Step 4: update names of existing users if changed ──────────────
        $updated = 0;
        foreach ($existingUsers as $email => $user) {
            $newName = $erpMap[$email] ?? null;
            if ($newName && $user->name !== $newName) {
                User::where('id', $user->id)->update(['name' => $newName, 'updated_at' => $now]);
                $updated++;
            }
        }

        // ── Step 5: ensure existing users without a Customer record get one ─
        $orphans = User::whereIn(DB::raw('LOWER(email)'), $existingEmails)
            ->where('role', 'customer')
            ->doesntHave('customer')
            ->select('id')
            ->get();

        if ($orphans->isNotEmpty()) {
            $orphanRows = $orphans->map(fn($u) => [
                'user_id'               => $u->id,
                'customer_code'         => 'ERP-' . strtoupper(Str::random(6)),
                'preferred_bill_format' => 'excel',
                'created_at'            => $now,
                'updated_at'            => $now,
            ])->all();
            DB::table('customers')->insert($orphanRows);
        }

        return response()->json([
            'status'  => 'success',
            'total'   => count($erpEmails),
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($erpCustomers) - count($erpEmails),
            'message' => "Sync complete. {$created} new accounts created, {$updated} updated.",
        ]);
    }


    /**
     * Get sync status — how many BMS customers vs external API customers.
     * GET /admin/sync/status
     */
    public function status()
    {
        $bmsCount = User::where('role', 'customer')->count();
        $linkedCount = Customer::whereNotNull('external_cucode')->count();

        return response()->json([
            'bms_customers'    => $bmsCount,
            'linked_customers' => $linkedCount,
        ]);
    }
}
