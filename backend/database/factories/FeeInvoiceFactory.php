<?php

namespace Database\Factories;

use App\Models\FeeInvoice;
use App\Models\LegacyFeeType;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeInvoice>
 */
class FeeInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_type_id' => LegacyFeeType::factory(),
            'academic_year' => now()->year.'-'.(now()->year + 1),
            'description' => fake()->words(2, true),
            'amount_cents' => fake()->numberBetween(10000, 500000),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
            'status' => 'open',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FeeInvoice $invoice): void {
            $feeType = LegacyFeeType::query()->findOrFail($invoice->fee_type_id);
            Student::query()->whereKey($invoice->student_id)->update(['school_id' => $feeType->school_id]);
            $invoice->school_id = $feeType->school_id;
            $invoice->academic_year = $feeType->academic_year;
            $invoice->description = $feeType->title;
            $invoice->amount_cents = $feeType->amount_cents;
        });
    }
}
