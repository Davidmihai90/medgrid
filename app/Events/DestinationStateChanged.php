<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DestinationStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $caseId,
        public readonly string $encounterId,
        public readonly string $eventName,
        public readonly array $data,
        public readonly string $correlationId,
    ) {
        $this->eventId = (string) Str::ulid();
        $this->occurredAt = now()->utc()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('case.'.$this->caseId), new PrivateChannel('destination.'.$this->encounterId)];
    }

    public function broadcastAs(): string
    {
        return 'destination.state.changed';
    }

    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'event' => $this->eventName, 'occurred_at' => $this->occurredAt, 'organization_id' => $this->organizationId, 'case_id' => $this->caseId, 'encounter_id' => $this->encounterId, 'correlation_id' => $this->correlationId, 'version' => 1, 'data' => $this->data];
    }
}
