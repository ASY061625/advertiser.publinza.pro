<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'name' => fake()->name(),
            'company' => fake()->company(),
            'country' => fake()->countryCode(),
            'vat_no' => fake()->boolean(40) ? strtoupper(fake()->countryCode()).fake()->numerify('#########') : null,
            'phone' => fake()->phoneNumber(),
            'timezone' => fake()->timezone(),
            'locale' => 'en',
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
            'referrer_source' => fake()->randomElement(['organic', 'google_ads', 'referral', 'linkedin', null]),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Suspended]);
    }

    /** Gives the user a funded wallet, which most flows assume exists. */
    public function withWallet(int $availableCents = 500_00): static
    {
        return $this->afterCreating(function (User $user) use ($availableCents): void {
            WalletFactory::new()->create([
                'user_id' => $user->id,
                'available_cents' => $availableCents,
            ]);
        });
    }
}
