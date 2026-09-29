<?php

namespace App\Domain\Destination\Enums;

enum DestinationRequirementType: string
{
    case CapabilityRequired = 'CAPABILITY_REQUIRED';
    case CapabilityPreferred = 'CAPABILITY_PREFERRED';
    case ResourceRequired = 'RESOURCE_REQUIRED';
    case ReceivingRequired = 'RECEIVING_REQUIRED';
}
