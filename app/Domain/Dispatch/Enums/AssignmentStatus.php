<?php

namespace App\Domain\Dispatch\Enums;

enum AssignmentStatus: string
{
    case Pending = 'PENDING';
    case Delivered = 'DELIVERED';
    case Accepted = 'ACCEPTED';
    case Cancelled = 'CANCELLED';
    case Completed = 'COMPLETED';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Delivered, self::Accepted], true);
    }
}
