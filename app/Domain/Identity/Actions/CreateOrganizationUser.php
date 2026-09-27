<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganizationUser
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Organization $organization, array $data): User
    {
        return DB::transaction(function () use ($organization, $data) {
            $roles = Role::where('scope', 'ORGANIZATION')->whereIn('id', $data['role_ids'] ?? [])->get();
            $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'], 'status' => $data['status']]);
            $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => MembershipStatus::Active]);
            $membership->roles()->sync($roles->modelKeys());
            $this->audit->record('user.created', $user, $organization, ['status' => $user->status->value]);
            $this->audit->record('organization_membership.created', $membership, $organization, ['role_ids' => $roles->modelKeys()]);

            return $user;
        });
    }
}
