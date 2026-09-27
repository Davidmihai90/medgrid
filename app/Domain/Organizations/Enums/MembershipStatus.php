<?php

namespace App\Domain\Organizations\Enums;

enum MembershipStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
}
