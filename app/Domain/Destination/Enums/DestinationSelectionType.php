<?php

namespace App\Domain\Destination\Enums;

enum DestinationSelectionType: string
{
    case EligibleSelection = 'ELIGIBLE_SELECTION';
    case UnknownOverride = 'UNKNOWN_OVERRIDE';
    case IneligibleOverride = 'INELIGIBLE_OVERRIDE';
    case ManualWithoutEvaluation = 'MANUAL_WITHOUT_EVALUATION';
}
