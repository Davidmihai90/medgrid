<?php

namespace App\Domain\Dispatch\Enums;

enum EmergencyCaseSource: string
{
    case Manual = 'MANUAL';
    case Simulation = 'SIMULATION';
}
