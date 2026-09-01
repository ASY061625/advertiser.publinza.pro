<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Billing\DTOs\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts an integer `*_cents` column to a Money value object and back.
 *
 * Usage: `'price_cents' => MoneyCast::class`. Assigning accepts a Money, a bare
 * integer of cents, or null; anything else is a programming error and throws
 * rather than silently rounding.
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currency = 'USD') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((int) $value, $this->currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Money) {
            return [$key => $value->cents];
        }

        if (is_int($value)) {
            return [$key => $value];
        }

        throw new InvalidArgumentException(
            sprintf('%s must be a Money instance or an integer number of cents, %s given.', $key, get_debug_type($value)),
        );
    }
}
