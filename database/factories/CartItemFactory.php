<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Website;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'website_id' => Website::factory(),
            'service_type' => ServiceType::ArticlePlacement,
            'content_mode' => ContentMode::AdvertiserProvides,
            'unit_price_cents' => $this->faker->numberBetween(50_00, 800_00),
            'express' => false,
        ];
    }
}
