<?php

namespace App\Domain\Hospitals\Enums;

enum ResourceStatus: string
{
    case Available = 'AVAILABLE';
    case Limited = 'LIMITED';
    case Full = 'FULL';
    case Unavailable = 'UNAVAILABLE';
    case Unknown = 'UNKNOWN';
}
