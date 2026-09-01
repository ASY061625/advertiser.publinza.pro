<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\Role;
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
            'email' => fake()->unique()->companyEmail(),
            'password' => Hash::make('password'),
            'name' => fake()->name(),
            'role_id' => Role::query()->firstOrCreate(
                ['name' => 'moderator'],
                ['label' => 'Moderator'],
            )->id,
            'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'status' => 'active',
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->firstOrCreate(['name' => 'owner'], ['label' => 'Owner'])->id,
        ]);
    }
}
