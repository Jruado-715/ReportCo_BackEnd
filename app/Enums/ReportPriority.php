<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportPriority: string
{
    case Unclassified = 'unclassified';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Emergency = 'emergency'; // triggers the Emergency Override pathway
}
