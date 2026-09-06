<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

use App\Domain\Messaging\Enums\SenderType;
use Illuminate\Http\UploadedFile;

final readonly class MessageData
{
    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function __construct(
        public string $body,
        public SenderType $senderType,
        /** Null for system messages, which have no author. */
        public ?int $senderId = null,
        /**
         * The browser's own id for this message, carried across retries so a
         * retried send that actually succeeded does not post twice. Null for
         * anything not typed by a person into a composer.
         */
        public ?string $clientToken = null,
        public array $attachments = [],
    ) {}
}
