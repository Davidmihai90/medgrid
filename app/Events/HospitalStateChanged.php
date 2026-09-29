<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class HospitalStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $hospitalId,
        public readonly string $organizationId,
        public readonly string $eventName,
        public readonly array $data,
        public readonly string $correlationId,
        public readonly ?string $caseId = null,
    ) {
        $this->eventId = (string) Str::ulid();
        $this->occurredAt = now()->utc()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('hospital.'.$this->hospitalId)];
        if ($this->caseId) {
            $channels[] = new PrivateChannel('case.'.$this->caseId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'hospital.state.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'event' => $this->eventName,
            'occurred_at' => $this->occurredAt,
            'organization_id' => $this->organizationId,
            'hospital_id' => $this->hospitalId,
            'correlation_id' => $this->correlationId,
            'version' => 1,
            'data' => $this->data,
        ];
    }
}
