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

class CustomerBillDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_bill_detail_falls_back_to_erp_bill_id_for_products(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'name' => 'Gold Win Medicals',
        ]);

        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'customer_code' => 'ERP-BTR62F',
            'external_cucode' => 'ERP-BTR62F',
        ]);

        $bill = Bill::factory()->create([
            'id' => 97673,
            'customer_id' => $customer->id,
            'invoice_no' => '3462',
            'grand_total' => 580,
        ]);

        $billing = Mockery::mock(ExternalBillingService::class);
        $billing->shouldReceive('getBillDetails')
            ->once()
            ->with('3462')
            ->andReturn([]);
        $billing->shouldReceive('getBillDetails')
            ->once()
            ->with('97673')
            ->andReturn([
                [
                    'ITEMNAME' => 'AMOXICILLIN CAPSULE',
                    'HSNCODE' => '30041090',
                    'QUANTITY' => 2,
                    'SRATE' => 250,
                    'GSTRATE' => 12,
                    'TOTALAMOUNT' => 580,
                    'cucode' => 'ERP-BTR62F',
                ],
            ]);

        $this->instance(ExternalBillingService::class, $billing);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/customer/bills/{$bill->id}")
            ->assertOk()
            ->assertJsonPath('line_items.0.ITEMNAME', 'AMOXICILLIN CAPSULE')
            ->assertJsonPath('lineItems.0.ITEMNAME', 'AMOXICILLIN CAPSULE');
    }
}
