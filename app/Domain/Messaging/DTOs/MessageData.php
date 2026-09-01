<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

use App\Domain\Messaging\Enums\SenderType;

final readonly class MessageData
{
    public function __construct(
        public string $body,
        public SenderType $senderType,
        /** Null for system messages, which have no author. */
        public ?int $senderId = null,
    ) {}
}
