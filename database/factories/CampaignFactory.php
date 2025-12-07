<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => 'Test Campaign',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'total_cost' => 100000,
            'status' => 'active',
        ];
    }
}