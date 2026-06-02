<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OverviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overview_metrics_use_bill_and_due_dates(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create();

        Bill::factory()->create([
            'customer_id' => $customer->id,
            'bill_date' => '2026-06-15',
            'due_date' => '2026-06-20',
            'grand_total' => 1000,
            'payment_status' => 'unpaid',
        ]);

        Bill::factory()->create([
            'customer_id' => $customer->id,
            'bill_date' => '2026-06-10',
            'due_date' => '2026-06-14',
            'grand_total' => 300,
            'payment_status' => 'proof_rejected',
        ]);

        Bill::factory()->create([
            'customer_id' => $customer->id,
            'bill_date' => '2026-06-15',
            'due_date' => '2026-07-05',
            'grand_total' => 2000,
            'payment_status' => 'unpaid',
        ]);

        Bill::factory()->create([
            'customer_id' => $customer->id,
            'bill_date' => '2026-06-15',
            'due_date' => '2026-06-25',
            'grand_total' => 400,
            'payment_status' => 'paid',
            'payment_verified_at' => '2026-06-15 09:30:00',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('bills_today', 3)
            ->assertJsonPath('dues_this_month', 1300)
            ->assertJsonPath('dues_this_month_count', 2)
            ->assertJsonPath('overdue_count', 1)
            ->assertJsonPath('overdue_amount', 300);
    }
}
