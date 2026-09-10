<?php

namespace Database\Factories;

use App\Enums\StepType;
use App\Models\Run;
use App\Models\RunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunStep>
 */
class RunStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'step_type' => fake()->randomElement(StepType::cases()),
            'config' => null,
            'result' => null,
            'status' => 'pending',
        ];
    }

    /**
     * Create a completed profile step with a sample result.
     */
    public function profileCompleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'step_type' => StepType::Profile,
            'status' => 'completed',
            'result' => [
                'row_count' => 1000,
                'column_count' => 10,
                'null_counts' => ['name' => 5, 'email' => 0],
                'duplicate_rows' => 12,
            ],
        ]);
    }

    /**
     * Create a completed clean step.
     */
    public function cleanCompleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'step_type' => StepType::Clean,
            'status' => 'completed',
            'config' => [
                'steps' => [
                    ['action' => 'drop_nulls', 'columns' => ['name']],
                    ['action' => 'deduplicate'],
                ],
            ],
            'result' => [
                'rows_before' => 1000,
                'rows_after' => 983,
                'rows_removed' => 17,
            ],
        ]);
    }
}
