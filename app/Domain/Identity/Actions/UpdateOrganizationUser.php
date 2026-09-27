<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateOrganizationUser
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Organization $organization, User $user, array $data, bool $mayChangeAccountStatus): User
    {
        return DB::transaction(function () use ($organization, $user, $data, $mayChangeAccountStatus) {
            $membership = $user->memberships()->where('organization_id', $organization->id)->lockForUpdate()->firstOrFail();
            $before = ['name' => $user->name, 'email' => $user->email, 'status' => $user->status->value, 'membership_status' => $membership->status->value];
            $user->fill(['name' => $data['name'], 'email' => strtolower($data['email'])]);
            if ($mayChangeAccountStatus && isset($data['status'])) {
                $user->status = $data['status'];
            }
            $user->save();
            $membership->update(['status' => $data['membership_status']]);
            $roles = Role::where('scope', 'ORGANIZATION')->whereIn('id', $data['role_ids'] ?? [])->get();
            $membership->roles()->sync($roles->modelKeys());
            $this->audit->record('user.updated', $user, $organization, ['before' => $before, 'fields' => ['name', 'email', 'status', 'membership_status']]);
            $this->audit->record('role.assignment.updated', $membership, $organization, ['role_ids' => $roles->modelKeys()]);

            return $user->refresh();
        });
    }
}
