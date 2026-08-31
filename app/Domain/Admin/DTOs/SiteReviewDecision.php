<?php

declare(strict_types=1);

namespace App\Domain\Admin\DTOs;

final readonly class SiteReviewDecision
{
    public function __construct(
        public bool $approved,
        public ?string $reason = null,
    ) {}
}
