<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum MetricSource: string
{
    case Ahrefs = 'ahrefs';
    case Moz = 'moz';
    case Semrush = 'semrush';
    case Similarweb = 'similarweb';
    case Manual = 'manual';
}
