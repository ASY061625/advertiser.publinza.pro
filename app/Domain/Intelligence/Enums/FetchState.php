<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Enums;

/**
 * Where a competitor row is between being added and having numbers.
 *
 * `failed` is not the same as having no metrics: a row can be `failed` and
 * still hold last week's figures, which is the case the amber "showing cached
 * data" notice exists for. The state describes the last fetch; the metric row
 * describes what is known.
 */
enum FetchState: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
