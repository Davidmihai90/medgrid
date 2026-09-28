<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DispatchStateChanged implements ShouldBroadcastNow
{
    use Dispatchable,InteractsWithSockets,SerializesModels;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(public readonly string $organizationId, public readonly string $eventName, public readonly array $data, public readonly string $correlationId, public readonly ?string $caseId = null, public readonly ?string $vehicleId = null)
    {
        $this->eventId = (string) Str::ulid();
        $this->occurredAt = now()->utc()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('dispatch.'.$this->organizationId)];
        if ($this->caseId) {
            $channels[] = new PrivateChannel('case.'.$this->caseId);
        }if ($this->vehicleId) {
            $channels[] = new PrivateChannel('vehicle.'.$this->vehicleId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'dispatch.state.changed';
    }

    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'event' => $this->eventName, 'occurred_at' => $this->occurredAt, 'organization_id' => $this->organizationId, 'correlation_id' => $this->correlationId, 'version' => 1, 'data' => $this->data];
    }
}
