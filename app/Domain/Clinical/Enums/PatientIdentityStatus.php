<?php

namespace App\Domain\Clinical\Enums;

enum PatientIdentityStatus: string
{
    case Unidentified = 'UNIDENTIFIED';
    case PartiallyIdentified = 'PARTIALLY_IDENTIFIED';
    case Identified = 'IDENTIFIED';
}
