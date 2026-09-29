<?php

namespace App\Domain\Hospitals\Enums;

enum RestrictionStatus: string
{
    case Active = 'ACTIVE';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
}
