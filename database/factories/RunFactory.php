<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_file_path' => 'uploads/'.fake()->uuid().'.csv',
            'source_file_type' => fake()->randomElement(['csv', 'xlsx', 'json', 'pdf', 'txt']),
            'status' => RunStatus::Pending,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Set the run status.
     */
    public function withStatus(RunStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Mark the run as profiled.
     */
    public function profiled(): static
    {
        return $this->withStatus(RunStatus::Profiled);
    }

    /**
     * Mark the run as failed.
     */
    public function failed(): static
    {
        return $this->withStatus(RunStatus::Failed);
    }
}
