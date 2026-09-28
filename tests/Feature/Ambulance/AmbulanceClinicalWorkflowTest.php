<?php

namespace Tests\Feature\Ambulance;

use App\Domain\Clinical\Enums\AssessmentStatus;
use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Events\ClinicalStateChanged;
use App\Models\Assessment;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\CaseVehicleAssignment;
use App\Models\ClinicalNote;
use App\Models\EmergencyCase;
use App\Models\OrganizationMembership;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCrewAssignment;
use App\Models\VitalObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class AmbulanceClinicalWorkflowTest extends TestCase
{
    use CreatesAuthorizationContext,RefreshDatabase;

    private function permissions(): array
    {
        return Permissions::M2;
    }

    private function mission(array $permissions = []): array
    {
        $x = $this->context($permissions ?: $this->permissions());
        $vehicle = Vehicle::create(['organization_id' => $x['organization']->id, 'callsign' => 'AMB-M2-'.Str::random(4), 'display_name' => 'Synthetic M2 Unit', 'status' => VehicleStatus::Assigned, 'is_active' => true]);
        VehicleCrewAssignment::create(['organization_id' => $x['organization']->id, 'vehicle_id' => $vehicle->id, 'user_id' => $x['user']->id, 'crew_role' => 'PARAMEDIC', 'started_at' => now()]);
        $case = EmergencyCase::create(['organization_id' => $x['organization']->id, 'case_number' => 'MG-2026-'.random_int(930000, 999999), 'source' => 'SIMULATION', 'status' => EmergencyCaseStatus::UnitAccepted, 'priority' => EmergencyCasePriority::P2, 'incident_type' => 'Synthetic M2 exercise', 'location_text' => 'Training location', 'summary' => 'Synthetic data only.', 'received_at' => now(), 'assigned_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        $assignment = CaseVehicleAssignment::create(['organization_id' => $x['organization']->id, 'emergency_case_id' => $case->id, 'vehicle_id' => $vehicle->id, 'status' => AssignmentStatus::Accepted, 'assigned_by' => $x['user']->id, 'assigned_at' => now(), 'accepted_by' => $x['user']->id, 'accepted_at' => now()]);

        return $x + compact('case', 'vehicle', 'assignment');
    }

    private function acting(array $x): static
    {
        app(CurrentOrganization::class)->clear();

        return $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id]);
    }

    private function encounter(array $x): PatientEncounter
    {
        foreach (['EN_ROUTE_TO_SCENE', 'ON_SCENE', 'PATIENT_CONTACT'] as $status) {
            $this->acting($x)->postJson('/api/v1/cases/'.$x['case']->id.'/workflow', ['status' => $status])->assertOk();
        }$this->acting($x)->postJson('/api/v1/cases/'.$x['case']->id.'/encounters', ['identity_status' => 'UNIDENTIFIED'])->assertCreated();

        return PatientEncounter::latest()->firstOrFail();
    }

    public function test_workflow_is_explicit_and_encounters_are_numbered_per_case(): void
    {
        $x = $this->mission();
        $this->acting($x)->postJson('/api/v1/cases/'.$x['case']->id.'/workflow', ['status' => 'ON_SCENE'])->assertStatus(409);
        $e = $this->encounter($x);
        $this->acting($x)->postJson('/api/v1/cases/'.$x['case']->id.'/encounters', ['identity_status' => 'PARTIALLY_IDENTIFIED', 'first_name' => 'Synthetic'])->assertCreated();
        $this->assertSame([$x['case']->case_number.'-P01', $x['case']->case_number.'-P02'], PatientEncounter::orderBy('encounter_index')->pluck('encounter_number')->all());
        $this->assertSame(VehicleStatus::OnScene, $x['vehicle']->fresh()->status);
    }

    public function test_vitals_are_append_only_and_blood_pressure_is_structured(): void
    {
        $x = $this->mission();
        $e = $this->encounter($x);
        $this->acting($x)->postJson('/api/v1/encounters/'.$e->id.'/vitals', ['type' => 'BLOOD_PRESSURE', 'value_numeric' => 120, 'measured_at' => now()->toIso8601String()])->assertUnprocessable();
        foreach ([94, 97] as $value) {
            $this->acting($x)->postJson('/api/v1/encounters/'.$e->id.'/vitals', ['type' => 'SPO2', 'value_numeric' => $value, 'unit' => '%', 'measured_at' => now()->toIso8601String()])->assertCreated();
        }$this->acting($x)->postJson('/api/v1/encounters/'.$e->id.'/vitals', ['type' => 'BLOOD_PRESSURE', 'value_numeric' => 120, 'secondary_value_numeric' => 80, 'unit' => 'mmHg', 'measured_at' => now()->toIso8601String()])->assertCreated();
        $this->assertSame(3, VitalObservation::count());
        $this->assertDatabaseHas('vital_observations', ['value_numeric' => '120.0000', 'secondary_value_numeric' => '80.0000']);
    }

    public function test_correction_preserves_original(): void
    {
        $x = $this->mission();
        $e = $this->encounter($x);
        $this->acting($x)->postJson('/api/v1/encounters/'.$e->id.'/vitals', ['type' => 'HEART_RATE', 'value_numeric' => 80, 'unit' => 'bpm', 'measured_at' => now()->toIso8601String()])->assertCreated();
        $v = VitalObservation::firstOrFail();
        $operationId = (string) Str::ulid();
        $correction = ['type' => 'HEART_RATE', 'value_numeric' => 88, 'unit' => 'bpm', 'measured_at' => now()->toIso8601String(), 'reason' => 'Transcription correction', 'operation_id' => $operationId];
        $this->acting($x)->postJson('/api/v1/vitals/'.$v->id.'/correct', $correction)->assertCreated();
        $this->acting($x)->postJson('/api/v1/vitals/'.$v->id.'/correct', $correction)->assertCreated();
        $this->assertSame(2, VitalObservation::count());
        $this->assertNotNull($v->fresh()->superseded_at);
        $this->assertDatabaseHas('vital_observations', ['supersedes_observation_id' => $v->id, 'correction_reason' => 'Transcription correction']);
    }

    public function test_completed_assessment_keeps_template_version_and_is_immutable(): void
    {
        $x = $this->mission();
        $e = $this->encounter($x);
        $t = AssessmentTemplate::create(['organization_id' => $x['organization']->id, 'code' => 'SYNTHETIC', 'name' => 'Synthetic assessment', 'is_synthetic' => true]);
        $v = AssessmentTemplateVersion::create(['assessment_template_id' => $t->id, 'version' => 1, 'definition' => ['fields' => [['key' => 'concern', 'required' => true]]], 'published_at' => now(), 'created_by' => $x['user']->id]);
        $this->acting($x)->postJson('/api/v1/cases/'.$x['case']->id.'/workflow', ['status' => 'ASSESSMENT'])->assertOk();
        $this->acting($x)->postJson('/api/v1/encounters/'.$e->id.'/assessments/'.$v->id)->assertCreated();
        $a = Assessment::firstOrFail();
        $this->acting($x)->putJson('/api/v1/assessments/'.$a->id.'/responses', ['responses' => ['concern' => 'Synthetic', 'unexpected' => 'Rejected']])->assertStatus(409);
        $this->assertSame(0, $a->responses()->count());
        $this->acting($x)->putJson('/api/v1/assessments/'.$a->id.'/responses', ['responses' => ['concern' => 'Synthetic']])->assertOk();
        $this->acting($x)->postJson('/api/v1/assessments/'.$a->id.'/complete')->assertOk();
        $this->assertSame(AssessmentStatus::Completed, $a->fresh()->status);
        $this->assertSame($v->id, $a->assessment_template_version_id);
        $this->acting($x)->putJson('/api/v1/assessments/'.$a->id.'/responses', ['responses' => ['concern' => 'Rewrite']])->assertStatus(409);
    }

    public function test_sync_is_idempotent(): void
    {
        $x = $this->mission();
        $e = $this->encounter($x);
        $id = (string) Str::ulid();
        $payload = ['operations' => [['operation_id' => $id, 'operation_type' => 'VITAL_CREATE', 'target_id' => $e->id, 'payload' => ['type' => 'RESPIRATORY_RATE', 'value_numeric' => 18, 'unit' => 'breaths/min', 'measured_at' => now()->toIso8601String()], 'captured_at' => now()->toIso8601String()]]];
        $this->acting($x)->postJson('/api/v1/sync/operations', $payload)->assertJsonPath('data.0.outcome', 'ACCEPTED');
        $this->acting($x)->postJson('/api/v1/sync/operations', $payload)->assertJsonPath('data.0.outcome', 'DUPLICATE');
        $this->assertSame(1, VitalObservation::where('operation_id', $id)->count());
    }

    public function test_cross_organization_resources_are_hidden_and_sync_rejected(): void
    {
        $owner = $this->mission();
        $e = $this->encounter($owner);
        $other = $this->mission();
        $this->acting($other)->getJson('/api/v1/encounters/'.$e->id)->assertNotFound();
        $this->acting($other)->postJson('/api/v1/sync/operations', ['operations' => [['operation_id' => (string) Str::ulid(), 'operation_type' => 'VITAL_CREATE', 'target_id' => $e->id, 'payload' => ['type' => 'SPO2', 'value_numeric' => 95, 'unit' => '%', 'measured_at' => now()->toIso8601String()], 'captured_at' => now()->toIso8601String()]]])->assertJsonPath('data.0.outcome', 'REJECTED');
    }

    public function test_patient_identity_evolves_with_optimistic_versioning(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $patient = $encounter->patient;

        $this->acting($context)->putJson('/api/v1/encounters/'.$encounter->id.'/patients/'.$patient->id, [
            'identity_status' => 'PARTIALLY_IDENTIFIED',
            'first_name' => 'Synthetic',
            'estimated_age_min' => 35,
            'estimated_age_max' => 45,
            'sex' => 'UNSPECIFIED',
            'entity_version' => 1,
        ])->assertOk()
            ->assertJsonMissingPath('data.national_identifier')
            ->assertJsonPath('data.version', 2);

        $this->acting($context)->putJson('/api/v1/encounters/'.$encounter->id.'/patients/'.$patient->id, [
            'identity_status' => 'IDENTIFIED',
            'first_name' => 'Stale update',
            'entity_version' => 1,
        ])->assertStatus(409);

        $this->assertSame('Synthetic', $patient->fresh()->first_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.identity.updated']);
    }

    public function test_gcs_components_are_preserved_and_total_is_explicitly_derived(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $measuredAt = now()->subMinutes(4)->startOfSecond();

        $this->acting($context)->postJson('/api/v1/encounters/'.$encounter->id.'/vitals', [
            'type' => 'GCS',
            'gcs_eye' => 4,
            'gcs_verbal' => 5,
            'gcs_motor' => 6,
            'measured_at' => $measuredAt->toIso8601String(),
        ])->assertCreated();

        $vital = VitalObservation::firstOrFail();
        $this->assertSame(15, $vital->gcs_total);
        $this->assertTrue($vital->gcs_total_derived);
        $this->assertTrue($vital->recorded_at->greaterThan($vital->measured_at));
        $this->assertTrue($vital->measured_at->equalTo($measuredAt));
    }

    public function test_non_crew_member_cannot_read_or_modify_clinical_context(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $outsider = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $outsider->id,
        ]);
        $membership->roles()->attach($context['role']);
        $outsiderContext = $context;
        $outsiderContext['user'] = $outsider;

        $this->acting($outsiderContext)->getJson('/api/v1/encounters/'.$encounter->id)->assertStatus(409);
        $this->acting($outsiderContext)->postJson('/api/v1/encounters/'.$encounter->id.'/vitals', [
            'type' => 'SPO2',
            'value_numeric' => 96,
            'unit' => '%',
            'measured_at' => now()->toIso8601String(),
        ])->assertStatus(409);
        $this->assertSame(0, VitalObservation::count());
    }

    public function test_clinical_channel_requires_the_assigned_crew_and_current_organization(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);

        $this->acting($context)->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-encounter.'.$encounter->id,
        ])->assertOk();

        $other = $this->mission();
        $this->acting($other)->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-encounter.'.$encounter->id,
        ])->assertForbidden();
    }

    public function test_clinical_realtime_payload_is_minimal_and_contains_no_identity(): void
    {
        $event = new ClinicalStateChanged(
            (string) Str::ulid(),
            (string) Str::ulid(),
            (string) Str::ulid(),
            'vital.recorded',
            ['vital_id' => (string) Str::ulid(), 'type' => 'SPO2'],
            (string) Str::ulid(),
        );
        $payload = $event->broadcastWith();

        $this->assertSame('clinical.state.changed', $event->broadcastAs());
        $this->assertArrayNotHasKey('patient', $payload);
        $this->assertArrayNotHasKey('national_identifier', $payload);
        $this->assertSame(['vital_id', 'type'], array_keys($payload['data']));
    }

    public function test_invalid_offline_vital_is_rejected_and_semantic_reuse_conflicts(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $operationId = (string) Str::ulid();
        $operation = [
            'operation_id' => $operationId,
            'operation_type' => 'VITAL_CREATE',
            'target_id' => $encounter->id,
            'payload' => [
                'type' => 'BLOOD_PRESSURE',
                'value_numeric' => 120,
                'unit' => 'mmHg',
                'measured_at' => now()->toIso8601String(),
            ],
            'captured_at' => now()->toIso8601String(),
        ];

        $this->acting($context)->postJson('/api/v1/sync/operations', ['operations' => [$operation]])
            ->assertJsonPath('data.0.outcome', 'REJECTED');
        $this->acting($context)->postJson('/api/v1/sync/operations', ['operations' => [$operation]])
            ->assertJsonPath('data.0.outcome', 'REJECTED');

        $operation['payload']['value_numeric'] = 121;
        $this->acting($context)->postJson('/api/v1/sync/operations', ['operations' => [$operation]])
            ->assertJsonPath('data.0.outcome', 'CONFLICT');

        $this->assertSame(0, VitalObservation::count());
    }

    public function test_offline_mutable_update_detects_entity_version_conflict(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $operation = fn (string $id, string $condition) => [
            'operation_id' => $id,
            'operation_type' => 'CONDITION_UPDATE',
            'target_id' => $encounter->id,
            'entity_version' => 1,
            'payload' => ['condition_level' => $condition],
            'captured_at' => now()->toIso8601String(),
        ];

        $this->acting($context)->postJson('/api/v1/sync/operations', [
            'operations' => [$operation((string) Str::ulid(), 'SERIOUS')],
        ])->assertJsonPath('data.0.outcome', 'ACCEPTED');

        $this->acting($context)->postJson('/api/v1/sync/operations', [
            'operations' => [$operation((string) Str::ulid(), 'STABLE')],
        ])->assertJsonPath('data.0.outcome', 'CONFLICT');

        $this->assertSame('SERIOUS', $encounter->fresh()->condition_level->value);
    }

    public function test_destination_pending_requires_a_completed_assessment(): void
    {
        $context = $this->mission();
        $encounter = $this->encounter($context);
        $this->acting($context)->postJson('/api/v1/cases/'.$context['case']->id.'/workflow', ['status' => 'ASSESSMENT'])->assertOk();
        $this->acting($context)->postJson('/api/v1/cases/'.$context['case']->id.'/workflow', ['status' => 'DESTINATION_PENDING'])->assertStatus(409);

        $template = AssessmentTemplate::create([
            'organization_id' => $context['organization']->id,
            'code' => 'SYNTHETIC-READY',
            'name' => 'Synthetic readiness assessment',
            'is_synthetic' => true,
        ]);
        $version = AssessmentTemplateVersion::create([
            'assessment_template_id' => $template->id,
            'version' => 1,
            'definition' => ['fields' => []],
            'published_at' => now(),
            'created_by' => $context['user']->id,
        ]);
        $this->acting($context)->postJson('/api/v1/encounters/'.$encounter->id.'/assessments/'.$version->id)->assertCreated();
        $assessment = Assessment::firstOrFail();
        $this->acting($context)->postJson('/api/v1/assessments/'.$assessment->id.'/complete')->assertOk();

        $this->acting($context)->postJson('/api/v1/cases/'.$context['case']->id.'/workflow', ['status' => 'DESTINATION_PENDING'])
            ->assertOk()
            ->assertJsonPath('data.status', 'DESTINATION_PENDING');
    }

    public function test_ambulance_workspace_contains_critical_mode_and_escapes_notes(): void
    {
        $context = $this->mission();
        $context['role']->update(['slug' => RoleSlugs::Paramedic]);
        $encounter = $this->encounter($context);
        ClinicalNote::create([
            'organization_id' => $context['organization']->id,
            'patient_encounter_id' => $encounter->id,
            'body' => '<script>alert("unsafe")</script>',
            'recorded_at' => now(),
            'recorded_by' => $context['user']->id,
        ]);

        $this->acting($context)->get('/ambulance?mission='.$context['assignment']->id.'&encounter='.$encounter->id)
            ->assertOk()
            ->assertSee('CRITICAL MODE')
            ->assertSee('data-offline-vital-form', false)
            ->assertSee($encounter->encounter_number)
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);
    }
}
