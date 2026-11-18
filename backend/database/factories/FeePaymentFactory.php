<?php

namespace Database\Factories;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePayment>
 */
class FeePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_invoice_id' => FeeInvoice::factory(),
            'amount_cents' => 5000,
            'paid_on' => now()->toDateString(),
            'reference' => fake()->optional()->bothify('REF-######'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FeePayment $payment): void {
            $payment->school_id = FeeInvoice::query()->findOrFail($payment->fee_invoice_id)->school_id;
        });
    }
}
