<?php

namespace App\Domain\Hospitals\Enums;

enum NotificationStatus: string
{
    case Pending = 'PENDING';
    case Delivered = 'DELIVERED';
    case Acknowledged = 'ACKNOWLEDGED';
    case Cancelled = 'CANCELLED';
}
