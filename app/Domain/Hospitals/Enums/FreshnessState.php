<?php

namespace App\Domain\Hospitals\Enums;

enum FreshnessState: string
{
    case Fresh = 'FRESH';
    case Aging = 'AGING';
    case Stale = 'STALE';
    case Unknown = 'UNKNOWN';
}
