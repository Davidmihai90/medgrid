<?php

namespace App\Domain\Hospitals\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Hospitals\Exceptions\HospitalStateConflict;
use App\Events\HospitalStateChanged;
use App\Models\CapabilityDefinition;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalDepartment;
use App\Models\Organization;
use App\Models\User;
use App\Support\CorrelationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HospitalConfigurationService
{
    public function __construct(private AuditRecorder $audit, private CorrelationContext $correlation) {}

    public function createHospital(Organization $organization, User $actor, array $data): Hospital
    {
        return DB::transaction(function () use ($organization, $actor, $data) {
            $hospital = Hospital::create([...$data, 'organization_id' => $organization->id, 'version' => 1]);
            DB::table('hospital_user_access')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->id,
                'hospital_id' => $hospital->id,
                'user_id' => $actor->id,
                'created_at' => now(),
            ]);
            $this->audit->record('hospital.created', $hospital, $organization, ['code' => $hospital->code], actor: $actor);

            return $hospital;
        });
    }

    public function createDepartment(Hospital $hospital, User $actor, array $data): HospitalDepartment
    {
        return DB::transaction(function () use ($hospital, $actor, $data) {
            $locked = Hospital::lockForUpdate()->findOrFail($hospital->id);
            $department = HospitalDepartment::create([...$data, 'organization_id' => $locked->organization_id, 'hospital_id' => $locked->id]);
            $locked->increment('version');
            $this->audit->record('hospital.department.created', $department, $locked->organization, ['hospital_id' => $locked->id, 'code' => $department->code], actor: $actor);

            return $department;
        });
    }

    public function addCapability(Hospital $hospital, CapabilityDefinition $definition, ?HospitalDepartment $department, User $actor, array $data): HospitalCapability
    {
        $capability = DB::transaction(function () use ($hospital, $definition, $department, $actor, $data) {
            $locked = $this->lockVersion($hospital, (int) $data['expected_version']);
            if ($department && $department->hospital_id !== $locked->id) {
                throw new HospitalStateConflict('Department does not belong to this hospital.');
            }
            if (HospitalCapability::where('hospital_id', $locked->id)->where('capability_definition_id', $definition->id)->where('hospital_department_id', $department?->id)->exists()) {
                throw new HospitalStateConflict('Capability is already configured for this hospital scope.');
            }
            $capability = HospitalCapability::create([
                'organization_id' => $locked->organization_id,
                'hospital_id' => $locked->id,
                'hospital_department_id' => $department?->id,
                'capability_definition_id' => $definition->id,
                'enabled' => $data['enabled'],
                'notes' => $data['notes'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
            ]);
            $locked->increment('version');
            $this->audit->record('hospital.capability.created', $capability, $locked->organization, ['hospital_id' => $locked->id, 'definition_id' => $definition->id, 'enabled' => $capability->enabled], actor: $actor);

            return $capability;
        });

        $this->broadcast($hospital, $capability);

        return $capability;
    }

    public function setCapabilityEnabled(HospitalCapability $capability, User $actor, bool $enabled, int $expectedVersion): HospitalCapability
    {
        $hospital = $capability->hospital;
        $capability = DB::transaction(function () use ($hospital, $capability, $actor, $enabled, $expectedVersion) {
            $locked = $this->lockVersion($hospital, $expectedVersion);
            $lockedCapability = HospitalCapability::lockForUpdate()->findOrFail($capability->id);
            $lockedCapability->update(['enabled' => $enabled]);
            $locked->increment('version');
            $this->audit->record('hospital.capability.updated', $lockedCapability, $locked->organization, ['hospital_id' => $locked->id, 'enabled' => $enabled], actor: $actor);

            return $lockedCapability;
        });

        $this->broadcast($hospital, $capability);

        return $capability;
    }

    private function lockVersion(Hospital $hospital, int $expectedVersion): Hospital
    {
        $locked = Hospital::lockForUpdate()->findOrFail($hospital->id);
        if ($locked->version !== $expectedVersion) {
            throw new HospitalStateConflict('Hospital state changed on the server. Reload before updating.');
        }

        return $locked;
    }

    private function broadcast(Hospital $hospital, HospitalCapability $capability): void
    {
        try {
            event(new HospitalStateChanged($hospital->id, $hospital->organization_id, 'hospital.capability.changed', ['capability_id' => $capability->id, 'enabled' => $capability->enabled, 'hospital_version' => $hospital->fresh()->version], $this->correlation->id()));
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Hospital capability realtime failed after persistence', ['hospital_id' => $hospital->id, 'correlation_id' => $this->correlation->id()]);
        }
    }
}
