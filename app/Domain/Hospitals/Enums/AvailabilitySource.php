<?php

namespace App\Domain\Hospitals\Enums;

enum AvailabilitySource: string
{
    case Manual = 'MANUAL';
    case Simulation = 'SIMULATION';
    case Integration = 'INTEGRATION';
}
