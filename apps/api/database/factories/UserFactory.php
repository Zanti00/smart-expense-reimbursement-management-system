<?php

namespace Database\Factories;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model the factory corresponds to.
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => fake()->name(),
            'email'      => fake()->unique()->safeEmail(),
            'role'       => fake()->randomElement(['employee', 'approver', 'admin']),
            'grade'      => fake()->randomElement(['L1', 'L2', 'L3', 'EXEC']),
            'department' => fake()->randomElement(['SALES', 'ACCOUNTING', 'ENGINEERING', 'HR']),
            'avatar'     => strtoupper(substr(fake()->name(), 0, 2)),
        ];
    }
}
