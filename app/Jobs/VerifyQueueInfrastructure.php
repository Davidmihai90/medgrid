<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class VerifyQueueInfrastructure implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $probeId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Cache::put('medgrid:queue-probe:'.$this->probeId, now()->utc()->toIso8601String(), 300);
    }
}
