<?php

namespace App\Domain\Hospitals\Enums;

enum HospitalStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Maintenance = 'MAINTENANCE';
}
