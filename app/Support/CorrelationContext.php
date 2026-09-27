<?php

namespace App\Support;

use Illuminate\Support\Str;

class CorrelationContext
{
    private ?string $id = null;

    public function id(): string
    {
        return $this->id ??= (string) Str::ulid();
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }
}
