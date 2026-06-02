<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_local_bill_download_uses_signed_stream_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('bills/invoice.pdf', 'pdf bytes');

        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'preferred_bill_format' => 'pdf',
        ]);

        $bill = Bill::factory()->create([
            'customer_id' => $customer->id,
            'bill_file_url' => 'bills/invoice.pdf',
            'bill_file_type' => 'pdf',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/customer/bills/{$bill->id}/download")
            ->assertOk()
            ->assertJsonStructure(['download_url']);

        $downloadUrl = $response->json('download_url');
        $this->assertStringContainsString("/api/v1/customer/bills/{$bill->id}/stream", $downloadUrl);
        $this->assertNotSame('bills/invoice.pdf', $downloadUrl);

        $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=invoice.pdf');
    }
}
