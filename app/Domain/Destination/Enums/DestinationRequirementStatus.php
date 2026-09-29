<?php

namespace App\Domain\Destination\Enums;

enum DestinationRequirementStatus: string
{
    case Active = 'ACTIVE';
    case Superseded = 'SUPERSEDED';
    case Cancelled = 'CANCELLED';
}
