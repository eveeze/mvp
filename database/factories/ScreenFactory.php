<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screen>
 */
class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    public function definition(): array
    {
        return [
            'hotel_id'          => Hotel::factory(),
            'name'              => $this->faker->sentence(2),
            'code'              => strtoupper('SCR-'.$this->faker->unique()->numberBetween(100, 999)),
            'location'          => $this->faker->randomElement(['Lobby', 'Lift Area', 'Restaurant', 'Reception']),
            'resolution_width'  => 1920,
            'resolution_height' => 1080,
            'orientation'       => $this->faker->randomElement(['landscape', 'portrait']),
            'is_online'         => $this->faker->boolean(80),
            'allowed_categories'=> ['food', 'travel'],
        ];
    }
}
