<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class OrganizationOperationalNotice implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(public readonly string $organizationId, public readonly string $message, public readonly string $correlationId)
    {
        $this->eventId = (string) Str::ulid();
        $this->occurredAt = now()->utc()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organization.'.$this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'organization.operational.notice';
    }

    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'event' => 'organization.operational.notice', 'occurred_at' => $this->occurredAt, 'organization_id' => $this->organizationId, 'correlation_id' => $this->correlationId, 'version' => 1, 'data' => ['message' => $this->message]];
    }
}
