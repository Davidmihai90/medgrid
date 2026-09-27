<?php

namespace App\Domain\Organizations\Services;

use App\Domain\Organizations\Enums\MembershipStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CurrentOrganization
{
    private ?Organization $resolved = null;

    private bool $attempted = false;

    public function get(): ?Organization
    {
        if ($this->attempted) {
            return $this->resolved;
        }
        $this->attempted = true;
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }
        $id = session('current_organization_id');
        if ($id) {
            $organization = Organization::find($id);
            if ($organization && $this->canUse($user, $organization)) {
                return $this->resolved = $organization;
            }
            session()->forget('current_organization_id');
        }
        $organization = $user->isPlatformSuperAdministrator()
            ? Organization::where('status', OrganizationStatus::Active->value)->orderBy('name')->first()
            : Organization::where('status', OrganizationStatus::Active->value)
                ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
                ->orderBy('name')->first();
        if ($organization) {
            session(['current_organization_id' => $organization->id]);
        }

        return $this->resolved = $organization;
    }

    public function set(User $user, Organization $organization): void
    {
        if (! $this->canUse($user, $organization)) {
            throw new AuthorizationException('You cannot access this organization.');
        }
        session(['current_organization_id' => $organization->id]);
        session()->regenerate();
        $this->resolved = $organization;
        $this->attempted = true;
    }

    public function clear(): void
    {
        session()->forget('current_organization_id');
        $this->resolved = null;
        $this->attempted = false;
    }

    public function availableFor(User $user)
    {
        if ($user->isPlatformSuperAdministrator()) {
            return Organization::where('status', OrganizationStatus::Active->value)->orderBy('name')->get();
        }

        return Organization::where('status', OrganizationStatus::Active->value)
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')->get();
    }

    private function canUse(User $user, Organization $organization): bool
    {
        if (! $user->isActive() || $organization->status !== OrganizationStatus::Active) {
            return false;
        }

        return $user->isPlatformSuperAdministrator() || $user->memberships()
            ->where('organization_id', $organization->id)->where('status', MembershipStatus::Active->value)->exists();
    }
}
