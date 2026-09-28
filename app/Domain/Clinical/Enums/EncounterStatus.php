<?php

namespace App\Domain\Clinical\Enums;

enum EncounterStatus: string
{
    case Active = 'ACTIVE';
    case Stabilized = 'STABILIZED';
    case Cancelled = 'CANCELLED';
}
