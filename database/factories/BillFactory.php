<?php

namespace Database\Factories;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    public function definition(): array
    {
        $billDate = $this->faker->dateTimeBetween('-90 days', 'now');
        $dueDate = (clone $billDate)->modify('+15 days');
        $subtotal = $this->faker->randomFloat(2, 1000, 50000);
        $gstTotal = $subtotal * 0.18;
        $grandTotal = $subtotal + $gstTotal;

        return [
            'customer_id' => Customer::factory(),
            'invoice_no' => 'INV-' . $this->faker->unique()->numberBetween(10000, 99999),
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'subtotal' => $subtotal,
            'gst_total' => $gstTotal,
            'grand_total' => $grandTotal,
            'status' => $dueDate < now() ? 'overdue' : 'unpaid',
            'payment_status' => 'unpaid',
        ];
    }
}
