<?php

namespace App\Domain\Hospitals\Enums;

enum ReasonCode: string
{
    case Capacity = 'CAPACITY';
    case Staffing = 'STAFFING';
    case Equipment = 'EQUIPMENT';
    case Maintenance = 'MAINTENANCE';
    case TemporaryRestriction = 'TEMPORARY_RESTRICTION';
    case InternalIncident = 'INTERNAL_INCIDENT';
    case Other = 'OTHER';
}
