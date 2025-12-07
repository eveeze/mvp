<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->company . ' Hotel',
            'city'           => $this->faker->city,
            'address'        => $this->faker->address,
            'contact_person' => $this->faker->name,
            'contact_phone'  => $this->faker->phoneNumber,
        ];
    }
}
