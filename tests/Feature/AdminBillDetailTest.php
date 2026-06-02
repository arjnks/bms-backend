<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use App\Services\ExternalBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminBillDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bill_detail_falls_back_to_erp_line_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $customerUser = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'name' => 'Gold Win Medicals',
        ]);

        $customer = Customer::factory()->create([
            'user_id' => $customerUser->id,
            'customer_code' => 'ERP-BTR62F',
        ]);

        $bill = Bill::factory()->create([
            'customer_id' => $customer->id,
            'invoice_no' => '99029',
            'grand_total' => 2038,
        ]);

        $billing = Mockery::mock(ExternalBillingService::class);
        $billing->shouldReceive('getBillDetails')
            ->once()
            ->with(99029)
            ->andReturn([
                [
                    'ITEMNAME' => 'PARACETAMOL 500MG TABLET',
                    'HSNCODE' => '30049099',
                    'QUANTITY' => 10,
                    'SRATE' => 12.5,
                    'GSTRATE' => 12,
                    'TOTALAMOUNT' => 140,
                ],
            ]);

        $this->instance(ExternalBillingService::class, $billing);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/bills/{$bill->id}")
            ->assertOk()
            ->assertJsonPath('line_items.0.ITEMNAME', 'PARACETAMOL 500MG TABLET')
            ->assertJsonPath('line_items.0.HSNCODE', '30049099');
    }
}
