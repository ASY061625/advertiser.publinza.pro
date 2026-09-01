<?php

declare(strict_types=1);

namespace App\Domain\Trading\Enums;

enum ContentMode: string
{
    case AdvertiserProvides = 'advertiser_provides';
    case PublisherWrites = 'publisher_writes';

    public function label(): string
    {
        return match ($this) {
            self::AdvertiserProvides => 'I provide the article',
            self::PublisherWrites => 'The publisher writes it',
        };
    }

    /** Only publisher-written placements carry a writing fee. */
    public function incursWritingFee(): bool
    {
        return $this === self::PublisherWrites;
    }
}
