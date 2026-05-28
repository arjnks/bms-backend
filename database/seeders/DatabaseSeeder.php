<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\ReminderRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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

        // 2. Create Reminder Rules (Default)
        ReminderRule::insert([
            ['trigger_type' => 'before_due', 'offset_days' => 3, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['trigger_type' => 'on_due', 'offset_days' => 0, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['trigger_type' => 'after_due', 'offset_days' => 2, 'send_time' => '09:00:00', 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Customers and Bills removed for clean production state.
    }
}
