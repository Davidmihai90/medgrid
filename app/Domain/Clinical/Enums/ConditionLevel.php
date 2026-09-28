<?php

namespace App\Domain\Clinical\Enums;

enum ConditionLevel: string
{
    case Critical = 'CRITICAL';
    case Serious = 'SERIOUS';
    case Moderate = 'MODERATE';
    case Stable = 'STABLE';
    case Unknown = 'UNKNOWN';
}
