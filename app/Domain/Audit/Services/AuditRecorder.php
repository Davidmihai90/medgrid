<?php

namespace App\Domain\Audit\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\CorrelationContext;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function __construct(private CorrelationContext $correlation) {}

    public function record(string $action, ?Model $resource = null, ?Organization $organization = null, array $metadata = [], string $result = 'SUCCESS', ?string $reason = null, ?User $actor = null): AuditLog
    {
        $request = request();
        $actor ??= auth()->user();

        return AuditLog::create([
            'occurred_at' => now(), 'actor_type' => $actor ? User::class : null, 'actor_id' => $actor?->id,
            'organization_id' => $organization?->id, 'action' => $action,
            'resource_type' => $resource ? $resource::class : null, 'resource_id' => $resource?->getKey(),
            'result' => $result, 'reason' => $reason, 'correlation_id' => $this->correlation->id(),
            'session_identifier' => $request->hasSession() ? $request->session()->getId() : null,
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'secret', 'authorization', 'cookie'];

        return collect($metadata)->reject(fn ($v, $k) => in_array(strtolower((string) $k), $blocked, true))->all();
    }
}
