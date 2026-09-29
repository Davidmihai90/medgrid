<?php

namespace App\Domain\Destination\Enums;

enum DestinationRequirementImportance: string
{
    case Required = 'REQUIRED';
    case Preferred = 'PREFERRED';
}
