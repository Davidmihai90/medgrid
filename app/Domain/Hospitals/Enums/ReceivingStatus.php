<?php

namespace App\Domain\Hospitals\Enums;

enum ReceivingStatus: string
{
    case Open = 'OPEN';
    case Limited = 'LIMITED';
    case NotReceiving = 'NOT_RECEIVING';
    case Unknown = 'UNKNOWN';
}
