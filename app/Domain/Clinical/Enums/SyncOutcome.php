<?php

namespace App\Domain\Clinical\Enums;

enum SyncOutcome: string
{
    case Accepted = 'ACCEPTED';
    case Duplicate = 'DUPLICATE';
    case Conflict = 'CONFLICT';
    case Rejected = 'REJECTED';
}
