<?php

namespace App\Domain\Dispatch\Enums;

enum VehicleStatus: string
{
    case Offline = 'OFFLINE';
    case Available = 'AVAILABLE';
    case Reserved = 'RESERVED';
    case Assigned = 'ASSIGNED';
    case EnRouteScene = 'EN_ROUTE_SCENE';
    case OnScene = 'ON_SCENE';
    case Unavailable = 'UNAVAILABLE';
}
