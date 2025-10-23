<?php

namespace Database\Factories;

use App\Models\ImportJob;
use App\Models\Portal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportJob>
 */
class ImportJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portal_id' => Portal::factory(),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'original_filename' => $this->faker->word() . '.csv',
            'stored_filepath' => 'storage/imports/' . $this->faker->uuid() . '.csv',
            'field_mappings' => [
                'name' => 'column_1',
                'email' => 'column_2',
                'phone' => 'column_3',
            ],
            'settings' => [
                'delimiter' => ',',
                'encoding' => 'utf-8',
            ],
            'total_rows' => $this->faker->numberBetween(10, 1000),
            'processed_rows' => $this->faker->numberBetween(0, 100),
            'error_details' => null,
        ];
    }

    /**
     * Indicate that the import job is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'processed_rows' => $attributes['total_rows'] ?? 100,
        ]);
    }

    /**
     * Indicate that the import job has errors.
     */
    public function withErrors(): static
    {
        return $this->state(fn(array $attributes) => [
            'error_details' => [
                [
                    'row' => 1,
                    'field' => 'email',
                    'error' => 'Invalid email format',
                ],
                [
                    'row' => 3,
                    'field' => 'name',
                    'error' => 'Name is required',
                ],
            ],
        ]);
    }
}
