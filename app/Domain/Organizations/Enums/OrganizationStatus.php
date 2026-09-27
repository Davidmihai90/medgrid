<?php

namespace App\Domain\Organizations\Enums;

enum OrganizationStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
