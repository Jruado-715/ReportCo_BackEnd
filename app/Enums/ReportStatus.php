<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case Received = 'received';
    case InProgress = 'in_progress';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
}
