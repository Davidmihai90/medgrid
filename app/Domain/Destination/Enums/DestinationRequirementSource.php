<?php

namespace App\Domain\Destination\Enums;

enum DestinationRequirementSource: string
{
    case Manual = 'MANUAL';
    case Protocol = 'PROTOCOL';
    case Integration = 'INTEGRATION';
}
