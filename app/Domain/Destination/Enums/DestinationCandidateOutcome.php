<?php

namespace App\Domain\Destination\Enums;

enum DestinationCandidateOutcome: string
{
    case Eligible = 'ELIGIBLE';
    case Ineligible = 'INELIGIBLE';
    case Unknown = 'UNKNOWN';
}
