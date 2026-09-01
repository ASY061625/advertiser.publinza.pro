<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'available_cents' => 0,
            'frozen_cents' => 0,
            'currency' => 'USD',
        ];
    }

    public function funded(int $cents): static
    {
        return $this->state(fn (): array => ['available_cents' => $cents]);
    }
}
