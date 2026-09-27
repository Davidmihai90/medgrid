<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Invited = 'INVITED';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Disabled = 'DISABLED';

    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }
}
