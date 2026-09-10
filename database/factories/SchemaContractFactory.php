<?php

namespace Database\Factories;

use App\Enums\ContractFormat;
use App\Models\SchemaContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchemaContract>
 */
class SchemaContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dev_team_name' => fake()->company().' Team',
            'format' => fake()->randomElement(ContractFormat::cases()),
            'schema_definition' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true],
                    ['name' => 'name', 'type' => 'string', 'required' => true],
                    ['name' => 'email', 'type' => 'string', 'required' => true],
                    ['name' => 'created_at', 'type' => 'datetime', 'required' => false],
                ],
            ],
            'created_by' => User::factory(),
        ];
    }

    /**
     * Set a specific format for the contract.
     */
    public function withFormat(ContractFormat $format): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => $format,
        ]);
    }
}
