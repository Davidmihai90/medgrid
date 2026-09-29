<?php

namespace App\Domain\Hospitals\Enums;

enum AvailabilityStatus: string
{
    case Available = 'AVAILABLE';
    case Limited = 'LIMITED';
    case Unavailable = 'UNAVAILABLE';
    case Unknown = 'UNKNOWN';
}
