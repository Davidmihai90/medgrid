<?php

namespace App\Domain\Clinical\Enums;

enum AssessmentStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Amended = 'AMENDED';
    case Cancelled = 'CANCELLED';
}
