<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_code' => 'CUST-' . $this->faker->unique()->numberBetween(1000, 9999),
            'gstin' => '32' . strtoupper($this->faker->bothify('?????####?#?#')),
            'credit_limit' => $this->faker->randomElement([50000, 100000, 200000, 500000]),
            'preferred_bill_format' => $this->faker->randomElement(['csv', 'excel', 'pdf']),
        ];
    }
}
