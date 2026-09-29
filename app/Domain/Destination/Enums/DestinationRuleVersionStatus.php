<?php

namespace App\Domain\Destination\Enums;

enum DestinationRuleVersionStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Retired = 'RETIRED';
}
