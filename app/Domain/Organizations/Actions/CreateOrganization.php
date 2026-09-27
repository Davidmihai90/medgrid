<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $organization = Organization::create($data);
            $this->audit->record('organization.created', $organization, $organization, ['fields' => array_keys($data)]);

            return $organization;
        });
    }
}
