<?php

namespace App\Domain\Dispatch\Enums;

enum EmergencyCaseStatus: string
{
    case Received = 'RECEIVED';
    case Triaged = 'TRIAGED';
    case UnitAssigned = 'UNIT_ASSIGNED';
    case UnitAccepted = 'UNIT_ACCEPTED';
    case EnRouteToScene = 'EN_ROUTE_TO_SCENE';
    case OnScene = 'ON_SCENE';
    case PatientContact = 'PATIENT_CONTACT';
    case Assessment = 'ASSESSMENT';
    case DestinationPending = 'DESTINATION_PENDING';
    case Cancelled = 'CANCELLED';
    case Closed = 'CLOSED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Closed], true);
    }
}
