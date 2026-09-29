<?php

namespace App\Domain\Destination\Enums;

enum DestinationEvidenceResult: string
{
    case Pass = 'PASS';
    case Fail = 'FAIL';
    case Unknown = 'UNKNOWN';
    case Preferred = 'PREFERRED';
}
