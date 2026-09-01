<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domain\Posts\Enums\PostStatus;
use DomainException;

/**
 * Thrown when something tries to move a post along an edge the lifecycle does
 * not have. The lifecycle in PostStatus::allowedTransitions() is the single
 * source of truth; this is what refusing an illegal move looks like.
 */
class InvalidStatusTransition extends DomainException
{
    public function __construct(
        public readonly PostStatus $from,
        public readonly PostStatus $to,
        public readonly ?int $postId = null,
    ) {
        $allowed = array_map(
            static fn (PostStatus $status): string => $status->value,
            $from->allowedTransitions(),
        );

        parent::__construct(sprintf(
            'Cannot move post%s from "%s" to "%s". Allowed from "%s": %s.',
            $postId === null ? '' : " #{$postId}",
            $from->value,
            $to->value,
            $from->value,
            $allowed === [] ? 'nothing, it is a terminal status' : implode(', ', $allowed),
        ));
    }
}
