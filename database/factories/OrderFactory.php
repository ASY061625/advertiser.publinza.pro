<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Enums\PaidFrom;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100_00, 4_000_00);

        return [
            'user_id' => User::factory(),
            'order_number' => Order::generateNumber(),
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'total_cents' => $subtotal,
            'currency' => 'USD',
            'status' => OrderStatus::Paid,
            'paid_from' => PaidFrom::Wallet,
            'paid_at' => now(),
        ];
    }
}
