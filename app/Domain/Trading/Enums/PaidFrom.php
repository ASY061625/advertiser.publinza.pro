<?php

declare(strict_types=1);

namespace App\Domain\Trading\Enums;

enum PaidFrom: string
{
    case Wallet = 'wallet';
    case Card = 'card';
}
