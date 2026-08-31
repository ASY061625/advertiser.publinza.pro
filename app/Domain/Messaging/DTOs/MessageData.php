<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

final readonly class MessageData
{
    public function __construct(
        public string $body,
        public string $authorType,
        public int $authorId,
    ) {}
}
