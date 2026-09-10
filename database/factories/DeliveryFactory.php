<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Run;
use App\Models\SchemaContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'schema_contract_id' => SchemaContract::factory(),
            'output_path' => 'exports/'.fake()->uuid().'.csv',
            'delivered_by' => User::factory(),
            'delivered_at' => now(),
        ];
    }
}
