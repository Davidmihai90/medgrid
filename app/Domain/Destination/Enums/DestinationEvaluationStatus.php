<?php

namespace App\Domain\Destination\Enums;

enum DestinationEvaluationStatus: string
{
    case Pending = 'PENDING';
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
