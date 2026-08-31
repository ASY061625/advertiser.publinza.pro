<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'password' => Hash::make('password'),
            'role' => 'moderator',
            'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ];
    }

    public function super(): static
    {
        return $this->state(fn (): array => ['role' => 'super']);
    }
}
