<?php

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Models\EmergencyCase;
use App\Models\Organization;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Broadcast;

$member = function (User $u, string $id): bool {
    $o = Organization::find($id);

    return $u->isActive() && $o?->status === OrganizationStatus::Active && ($u->isPlatformSuperAdministrator() || $u->memberships()->where('organization_id', $id)->where('status', MembershipStatus::Active->value)->exists());
};
Broadcast::channel('organization.{organizationId}', $member);
Broadcast::channel('user.{userId}', fn (User $u, string $id) => $u->isActive() && hash_equals($u->id, $id));
Broadcast::channel('organization-presence.{organizationId}', fn (User $u, string $id) => $member($u, $id) ? ['id' => $u->id, 'name' => $u->name] : false);
Broadcast::channel('dispatch.{organizationId}', fn (User $u, string $id) => $member($u, $id) && $u->hasPermission(Permissions::CasesView, Organization::find($id)));
Broadcast::channel('case.{caseId}', function (User $u, string $id) {
    $c = EmergencyCase::find($id);

    return $c && $u->hasPermission(Permissions::CasesView, $c->organization);
});
Broadcast::channel('vehicle.{vehicleId}', function (User $u, string $id) {
    $v = Vehicle::find($id);

    return $v && $u->hasPermission(Permissions::AssignmentsView, $v->organization);
});

Broadcast::channel('encounter.{encounterId}', function (User $u, string $id) {
    $encounter = PatientEncounter::find($id);

    return $encounter && $u->can('view', $encounter);
});
