<?php

namespace App\Domain\Clinical\Enums;

enum ObservationSource: string
{
    case Manual = 'MANUAL';
    case Device = 'DEVICE';
    case Imported = 'IMPORTED';
    case OfflineSync = 'OFFLINE_SYNC';
}
