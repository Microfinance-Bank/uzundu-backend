<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CustomerStakeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Str::random(10),
            'stake_price' => $this->faker->numberBetween($min = 1500, $max = 6000),
            'stake_number' => $this->faker->numberBetween($min = 1, $max = 150),
            'month' => $this->faker->numberBetween($min = 1, $max = 12),
            'winning_tags_id' => $this->faker->numberBetween($min = 1, $max = 6),
            'category_id' => $this->faker->numberBetween($min = 1, $max = 3),
            'year' => 2023,
            'payment_method' => $this->faker->randomElement(['card','credit']),

        ];
    }
}
