<?php

use App\Domain\Organizations\Enums\MembershipStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('organization.{organizationId}', function (User $user, string $organizationId) {
    $org = Organization::find($organizationId);
    if (! $user->isActive() || ! $org || $org->status !== OrganizationStatus::Active) {
        return false;
    }

return $user->isPlatformSuperAdministrator() || $user->memberships()->where('organization_id', $organizationId)->where('status', MembershipStatus::Active->value)->exists();
});
Broadcast::channel('user.{userId}', fn (User $user, string $userId) => $user->isActive() && hash_equals($user->id, $userId));
Broadcast::channel('organization-presence.{organizationId}', function (User $user, string $organizationId) {
    $allowed = $user->isPlatformSuperAdministrator() || $user->memberships()->where('organization_id', $organizationId)->where('status', MembershipStatus::Active->value)->exists();

    return $user->isActive() && $allowed ? ['id' => $user->id, 'name' => $user->name] : false;
});
