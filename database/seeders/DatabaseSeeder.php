<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\ReminderRule;
use App\Models\ErpBillStatus;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // 1. Create Admin
        User::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@leogroup.com',
            'phone' => '+919876543210',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // 2. Reminder Rules
        ReminderRule::insert([
            ['trigger_type' => 'before_due', 'offset_days' => 3, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['trigger_type' => 'on_due', 'offset_days' => 0, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['trigger_type' => 'after_due', 'offset_days' => 2, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Create 50 Customers
        $customers = [];
        for ($i = 1; $i <= 50; $i++) {
            $user = User::create([
                'name' => $faker->company,
                'username' => 'customer' . $i,
                'email' => "customer{$i}@demo.com",
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'active',
            ]);
            
            $erpCode = str_pad($i, 6, '0', STR_PAD_LEFT);
            
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_code' => $erpCode,
                'external_cucode' => '050' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'gstin' => '32AA' . strtoupper($faker->bothify('?????####?')) . '1Z5',
                'credit_limit' => $faker->randomElement([50000, 100000, 200000, 500000]),
            ]);
            
            $customers[] = ['user' => $user, 'customer' => $customer];
        }

        // 4. Create 500 Bills
        $statuses = ['paid', 'unpaid', 'proof_rejected', 'payment_submitted'];
        
        for ($i = 1; $i <= 500; $i++) {
            $customerData = $faker->randomElement($customers);
            $user = $customerData['user'];
            $customer = $customerData['customer'];
            
            // Random date in last 6 months
            $billDate = Carbon::now()->subDays(rand(1, 180));
            $dueDate = (clone $billDate)->addDays(15);
            $paymentStatus = $faker->randomElement($statuses);
            
            $grandTotal = $faker->randomFloat(2, 500, 25000);
            
            $bill = Bill::create([
                'customer_id' => $customer->id,
                'invoice_no' => 'LPH/2627/' . str_pad(100000 + $i, 6, '0', STR_PAD_LEFT),
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'subtotal' => $grandTotal * 0.82,
                'gst_total' => $grandTotal * 0.18,
                'grand_total' => $grandTotal,
                'payment_status' => $paymentStatus,
                'payment_verified_at' => $paymentStatus === 'paid' ? (clone $dueDate)->subDays(rand(1, 5)) : null,
                'bill_file_url' => 'https://example.com/demo.pdf',
            ]);
            
            // 2-5 items per bill
            $itemsCount = rand(2, 5);
            for ($j = 0; $j < $itemsCount; $j++) {
                BillLineItem::create([
                    'bill_id' => $bill->id,
                    'product_name' => 'Medicine ' . $faker->word,
                    'qty' => rand(1, 50),
                    'rate' => rand(10, 500),
                    'gst_pct' => 18,
                    'line_total' => rand(50, 2500),
                ]);
            }
        }
        
        // 5. Populate ERP Bill Statuses (for the overview dashboard)
        // Generate 1500 bills for "Today" so the dashboard looks highly active!
        $today = Carbon::today();
        
        $erpRecords = [];
        for ($k = 1; $k <= 1500; $k++) {
            $erpRecords[] = [
                'billno'      => 'ERP/' . $today->format('Ymd') . '/' . str_pad($k, 5, '0', STR_PAD_LEFT),
                'date'        => clone $today,
                'cucode'      => '050' . rand(100, 999),
                'cuname'      => $faker->company,
                'netamount'   => rand(1000, 50000),
                'amtreceived' => 0,
                'settled'     => 'N',
                'ddays'       => 0,
                'lockdays'    => 15,
                'updated_at'  => now(),
            ];
            
            // Insert in chunks of 500
            if ($k % 500 === 0) {
                ErpBillStatus::insert($erpRecords);
                $erpRecords = [];
            }
        }
    }
}
