<?php

namespace App\Domain\Dispatch\Enums;

enum EmergencyCasePriority: string
{
    case P1 = 'P1';
    case P2 = 'P2';
    case P3 = 'P3';
    case P4 = 'P4';
    case P5 = 'P5';
    case Unknown = 'UNKNOWN';
}
