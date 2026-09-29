<?php

namespace Database\Seeders;

use App\Domain\Clinical\Enums\ClinicalKnowledgeStatus;
use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Enums\EncounterStatus;
use App\Domain\Clinical\Enums\PatientIdentityStatus;
use App\Domain\Destination\Enums\DestinationRequirementImportance;
use App\Domain\Destination\Enums\DestinationRequirementSource;
use App\Domain\Destination\Enums\DestinationRequirementStatus;
use App\Domain\Destination\Enums\DestinationRequirementType;
use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use App\Models\DestinationRequirement;
use App\Models\DestinationRuleSet;
use App\Models\DestinationRuleSetVersion;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientEncounter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DestinationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ems = Organization::where('slug', 'demo-emergency-service')->firstOrFail();
        $coordinator = User::where('email', 'coordinator.a@medgrid.test')->firstOrFail();
        $hospitals = Hospital::whereHas('organization', fn ($query) => $query->where('slug', 'demo-hospital-network'))->get();

        foreach ($hospitals as $hospital) {
            DB::table('destination_hospital_access')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'organization_id' => $ems->id,
                'hospital_id' => $hospital->id,
                'active' => true,
                'granted_by' => $coordinator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('destination_hospital_access')
                ->where('organization_id', $ems->id)
                ->where('hospital_id', $hospital->id)
                ->update(['active' => true, 'updated_at' => now()]);
        }

        $ruleSet = DestinationRuleSet::updateOrCreate(
            ['organization_id' => $ems->id, 'code' => 'DEMO-OPERATIONAL-COMPATIBILITY'],
            ['name' => 'MEDGRID DEMO — NOT A CLINICAL PROTOCOL', 'description' => 'Synthetic operational compatibility rules for local development only.', 'is_synthetic' => true],
        );
        DestinationRuleSetVersion::updateOrCreate(
            ['destination_rule_set_id' => $ruleSet->id, 'version' => 1],
            [
                'organization_id' => $ems->id,
                'status' => DestinationRuleVersionStatus::Active,
                'definition' => [
                    'engine_version' => 'M4-1',
                    'receiving' => ['limited_result' => 'FAIL'],
                    'freshness' => ['stale_result' => 'UNKNOWN'],
                    'capability' => ['missing_result' => 'FAIL', 'limited_result' => 'UNKNOWN'],
                    'resource' => ['missing_result' => 'UNKNOWN', 'limited_result' => 'PASS', 'require_positive_capacity' => true],
                    'restrictions' => ['active_result' => 'FAIL'],
                ],
                'created_by' => $coordinator->id,
                'activated_by' => $coordinator->id,
                'activated_at' => now(),
            ],
        );

        $case = EmergencyCase::where('organization_id', $ems->id)->where('idempotency_key', 'seed-m3-incoming-case')->firstOrFail();
        $patient = Patient::updateOrCreate(
            ['organization_id' => $ems->id, 'national_identifier' => 'SYNTHETIC-M4-001'],
            ['identity_status' => PatientIdentityStatus::Unidentified, 'estimated_age_min' => 40, 'estimated_age_max' => 60, 'version' => 1, 'created_by' => $coordinator->id, 'updated_by' => $coordinator->id],
        );
        $encounter = PatientEncounter::updateOrCreate(
            ['emergency_case_id' => $case->id, 'encounter_index' => 1],
            [
                'organization_id' => $ems->id,
                'patient_id' => $patient->id,
                'encounter_number' => 'MG-DEMO-HOSP-001-P1',
                'status' => EncounterStatus::Active,
                'condition_level' => ConditionLevel::Unknown,
                'allergy_status' => ClinicalKnowledgeStatus::Unknown,
                'medication_status' => ClinicalKnowledgeStatus::Unknown,
                'history_status' => ClinicalKnowledgeStatus::Unknown,
                'contact_at' => now()->subMinutes(16),
                'assessment_started_at' => now()->subMinutes(14),
                'assessment_completed_at' => now()->subMinutes(10),
                'version' => 1,
                'destination_version' => 1,
                'created_by' => $coordinator->id,
                'updated_by' => $coordinator->id,
            ],
        );

        foreach ([
            [DestinationRequirementType::ReceivingRequired, 'OPEN', DestinationRequirementImportance::Required],
            [DestinationRequirementType::CapabilityRequired, 'CT', DestinationRequirementImportance::Required],
            [DestinationRequirementType::ResourceRequired, 'ICU_BEDS', DestinationRequirementImportance::Required],
        ] as [$type, $target, $importance]) {
            DestinationRequirement::updateOrCreate(
                ['patient_encounter_id' => $encounter->id, 'type' => $type->value, 'target_code' => $target, 'status' => DestinationRequirementStatus::Active->value],
                [
                    'organization_id' => $ems->id,
                    'emergency_case_id' => $case->id,
                    'importance' => $importance,
                    'source' => DestinationRequirementSource::Manual,
                    'notes' => 'Explicit synthetic M4 demo requirement; not inferred from clinical data.',
                    'entered_by' => $coordinator->id,
                    'confirmed_by' => $coordinator->id,
                ],
            );
        }
    }
}
