<?php

namespace Tests\Feature\Destination;

use App\Domain\Destination\Enums\DestinationCandidateOutcome;
use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\AvailabilityStatus;
use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Hospitals\Enums\ReceivingStatus;
use App\Domain\Hospitals\Enums\ResourceStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Events\DestinationStateChanged;
use App\Models\CapabilityDefinition;
use App\Models\DestinationEvaluation;
use App\Models\DestinationRequirement;
use App\Models\DestinationRuleSet;
use App\Models\DestinationRuleSetVersion;
use App\Models\DestinationSelection;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCapabilityAvailability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalReceivingStatus;
use App\Models\HospitalResourceDefinition;
use App\Models\HospitalResourceState;
use App\Models\PatientEncounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class DestinationSupportTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([DestinationStateChanged::class]);
    }

    private function destinationContext(array $permissions = Permissions::M4): array
    {
        $context = $this->context([...$permissions, Permissions::CasesView, Permissions::CasesUpdate]);
        $case = EmergencyCase::create([
            'organization_id' => $context['organization']->id,
            'case_number' => 'MG-M4-'.random_int(100000, 999999),
            'source' => EmergencyCaseSource::Simulation,
            'status' => EmergencyCaseStatus::DestinationPending,
            'priority' => EmergencyCasePriority::P1,
            'incident_type' => 'Synthetic M4 exercise',
            'location_text' => 'Synthetic location',
            'summary' => 'Synthetic data only.',
            'received_at' => now(),
            'version' => 1,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $encounter = PatientEncounter::create([
            'organization_id' => $context['organization']->id,
            'emergency_case_id' => $case->id,
            'encounter_index' => 1,
            'encounter_number' => $case->case_number.'-P01',
            'status' => 'ACTIVE',
            'condition_level' => 'UNKNOWN',
            'allergy_status' => 'UNKNOWN',
            'medication_status' => 'UNKNOWN',
            'history_status' => 'UNKNOWN',
            'version' => 1,
            'destination_version' => 1,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $ruleSet = DestinationRuleSet::create(['organization_id' => $context['organization']->id, 'code' => 'TEST-M4', 'name' => 'MEDGRID DEMO — NOT A CLINICAL PROTOCOL', 'is_synthetic' => true]);
        $ruleVersion = DestinationRuleSetVersion::create([
            'organization_id' => $context['organization']->id,
            'destination_rule_set_id' => $ruleSet->id,
            'version' => 1,
            'status' => DestinationRuleVersionStatus::Active,
            'definition' => [
                'engine_version' => 'M4-TEST-1',
                'receiving' => ['limited_result' => 'FAIL'],
                'freshness' => ['stale_result' => 'UNKNOWN'],
                'capability' => ['missing_result' => 'FAIL', 'limited_result' => 'UNKNOWN'],
                'resource' => ['missing_result' => 'UNKNOWN', 'limited_result' => 'PASS', 'require_positive_capacity' => true],
                'restrictions' => ['active_result' => 'FAIL'],
            ],
            'created_by' => $context['user']->id,
            'activated_by' => $context['user']->id,
            'activated_at' => now(),
        ]);

        return $context + compact('case', 'encounter', 'ruleSet', 'ruleVersion');
    }

    private function acting(array $context): static
    {
        app(CurrentOrganization::class)->clear();

        return $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id]);
    }

    private function hospital(array $context, string $code, string $receiving, string $capabilityStatus, int $ageMinutes = 2): Hospital
    {
        $owner = $this->context();
        $hospital = Hospital::create(['organization_id' => $owner['organization']->id, 'code' => $code, 'name' => 'Synthetic '.$code.' Hospital', 'status' => HospitalStatus::Active, 'address' => 'Synthetic campus', 'active' => true, 'version' => 1]);
        DB::table('destination_hospital_access')->insert(['id' => (string) Str::ulid(), 'organization_id' => $context['organization']->id, 'hospital_id' => $hospital->id, 'active' => true, 'granted_by' => $context['user']->id, 'created_at' => now(), 'updated_at' => now()]);
        HospitalReceivingStatus::create(['organization_id' => $owner['organization']->id, 'hospital_id' => $hospital->id, 'status' => ReceivingStatus::from($receiving), 'effective_at' => now()->subMinutes(2), 'reported_by' => $owner['user']->id, 'source' => AvailabilitySource::Simulation]);
        $definition = CapabilityDefinition::firstOrCreate(['code' => 'CT'], ['name' => 'CT', 'active' => true, 'is_synthetic' => true]);
        $capability = HospitalCapability::create(['organization_id' => $owner['organization']->id, 'hospital_id' => $hospital->id, 'capability_definition_id' => $definition->id, 'enabled' => true]);
        HospitalCapabilityAvailability::create(['organization_id' => $owner['organization']->id, 'hospital_id' => $hospital->id, 'hospital_capability_id' => $capability->id, 'status' => AvailabilityStatus::from($capabilityStatus), 'effective_at' => now()->subMinutes($ageMinutes), 'reported_by' => $owner['user']->id, 'source' => AvailabilitySource::Simulation]);
        $resource = HospitalResourceDefinition::firstOrCreate(['code' => 'ICU_BEDS'], ['name' => 'ICU beds', 'active' => true, 'is_synthetic' => true]);
        HospitalResourceState::create(['organization_id' => $owner['organization']->id, 'hospital_id' => $hospital->id, 'hospital_resource_definition_id' => $resource->id, 'total_capacity' => 8, 'available_capacity' => 2, 'status' => ResourceStatus::Available, 'effective_at' => now()->subMinutes(2), 'reported_by' => $owner['user']->id, 'source' => AvailabilitySource::Simulation]);

        return $hospital;
    }

    private function addRequirements(array $context, PatientEncounter $encounter): void
    {
        foreach ([['RECEIVING_REQUIRED', 'OPEN', 'REQUIRED'], ['CAPABILITY_REQUIRED', 'CT', 'REQUIRED'], ['RESOURCE_REQUIRED', 'ICU_BEDS', 'REQUIRED']] as [$type, $code, $importance]) {
            $this->acting($context)->postJson('/api/v1/encounters/'.$encounter->id.'/destination-requirements', ['type' => $type, 'target_code' => $code, 'importance' => $importance, 'source' => 'MANUAL'])->assertSuccessful();
        }
    }

    private function evaluate(array $context, PatientEncounter $encounter): DestinationEvaluation
    {
        $this->acting($context)->postJson('/api/v1/encounters/'.$encounter->id.'/destination-evaluations', ['rule_version_id' => $context['ruleVersion']->id, 'idempotency_key' => (string) Str::ulid()])->assertOk();

        return DestinationEvaluation::where('patient_encounter_id', $encounter->id)->latest()->firstOrFail()->load('candidates.hospital');
    }

    public function test_requirements_are_explicit_append_only_and_authorized(): void
    {
        $context = $this->destinationContext();
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-requirements';
        $response = $this->acting($context)->postJson($url, ['type' => 'CAPABILITY_REQUIRED', 'target_code' => 'ct', 'importance' => 'REQUIRED', 'source' => 'MANUAL'])->assertSuccessful()->assertJsonPath('data.target_code', 'CT');
        $requirement = DestinationRequirement::findOrFail($response->json('data.id'));

        $this->acting($context)->putJson($url.'/'.$requirement->id, ['type' => 'CAPABILITY_PREFERRED', 'target_code' => 'MRI', 'importance' => 'PREFERRED', 'source' => 'MANUAL'])->assertSuccessful();
        $this->assertDatabaseHas('destination_requirements', ['id' => $requirement->id, 'status' => 'SUPERSEDED']);
        $this->assertDatabaseHas('destination_requirements', ['supersedes_requirement_id' => $requirement->id, 'target_code' => 'MRI', 'status' => 'ACTIVE']);

        $other = $this->destinationContext();
        $this->acting($other)->postJson($url, ['type' => 'CAPABILITY_REQUIRED', 'target_code' => 'CT', 'importance' => 'REQUIRED', 'source' => 'MANUAL'])->assertNotFound();
    }

    public function test_evaluation_produces_three_outcomes_and_preserves_snapshot(): void
    {
        $context = $this->destinationContext();
        $central = $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        $this->hospital($context, 'NORTH', 'OPEN', 'UNAVAILABLE');
        $this->hospital($context, 'TRAUMA', 'OPEN', 'AVAILABLE', 70);
        $this->addRequirements($context, $context['encounter']);

        $evaluation = $this->evaluate($context, $context['encounter']);
        $this->assertSame(['ELIGIBLE', 'INELIGIBLE', 'UNKNOWN'], $evaluation->candidates->pluck('outcome')->map->value->sort()->values()->all());
        $candidate = $evaluation->candidates->firstWhere('hospital_id', $central->id);
        $this->assertNotEmpty($candidate->evidence_data['checks']);
        $this->assertSame('M4-TEST-1', $evaluation->ruleVersion->definition['engine_version']);

        $availability = HospitalCapabilityAvailability::where('hospital_id', $central->id)->firstOrFail();
        $availability->update(['status' => AvailabilityStatus::Unavailable]);
        $this->assertSame('AVAILABLE', collect($candidate->fresh()->snapshot_data['capabilities'])->firstWhere('code', 'CT')['availability']['value']);
    }

    public function test_evaluation_retry_is_idempotent_and_preference_does_not_make_candidate_ineligible(): void
    {
        $context = $this->destinationContext();
        $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        $payload = ['type' => 'CAPABILITY_PREFERRED', 'target_code' => 'MISSING_OPTION', 'importance' => 'PREFERRED', 'source' => 'MANUAL'];
        $this->acting($context)->postJson('/api/v1/encounters/'.$context['encounter']->id.'/destination-requirements', $payload)->assertSuccessful();
        $key = (string) Str::ulid();
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-evaluations';
        $body = ['rule_version_id' => $context['ruleVersion']->id, 'idempotency_key' => $key];
        $this->acting($context)->postJson($url, $body)->assertOk();
        $this->acting($context)->postJson($url, $body)->assertOk();

        $this->assertSame(1, DestinationEvaluation::count());
        $this->assertSame(DestinationCandidateOutcome::Eligible, DestinationEvaluation::firstOrFail()->candidates()->firstOrFail()->outcome);
    }

    public function test_selection_is_human_audited_idempotent_and_notifies_once(): void
    {
        $context = $this->destinationContext();
        $central = $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        $this->addRequirements($context, $context['encounter']);
        $evaluation = $this->evaluate($context, $context['encounter']);
        $candidate = $evaluation->candidates->firstWhere('hospital_id', $central->id);
        $key = (string) Str::ulid();
        $payload = ['hospital_id' => $central->id, 'candidate_id' => $candidate->id, 'expected_destination_version' => 1, 'idempotency_key' => $key];
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-selections';

        $this->acting($context)->postJson($url, $payload)->assertOk()->assertJsonPath('data.selection_type', 'ELIGIBLE_SELECTION');
        $this->acting($context)->postJson($url, $payload)->assertOk();

        $this->assertSame(1, DestinationSelection::count());
        $this->assertSame(1, HospitalCaseNotification::where('patient_encounter_id', $context['encounter']->id)->count());
        $this->assertSame(EmergencyCaseStatus::DestinationSelected, $context['case']->fresh()->status);
        $this->assertDatabaseHas('case_events', ['emergency_case_id' => $context['case']->id, 'event_type' => 'DestinationSelected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'destination.selection.created']);
    }

    public function test_override_requires_permission_and_reason(): void
    {
        $context = $this->destinationContext();
        $north = $this->hospital($context, 'NORTH', 'OPEN', 'UNAVAILABLE');
        $this->addRequirements($context, $context['encounter']);
        $candidate = $this->evaluate($context, $context['encounter'])->candidates->firstWhere('hospital_id', $north->id);
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-selections';
        $payload = ['hospital_id' => $north->id, 'candidate_id' => $candidate->id, 'expected_destination_version' => 1, 'idempotency_key' => (string) Str::ulid()];

        $this->acting($context)->postJson($url, $payload)->assertStatus(409)->assertJsonPath('error.code', 'DESTINATION_CONFLICT');
        $payload['reason'] = 'Synthetic operational decision by authorized coordinator.';
        $this->acting($context)->postJson($url, $payload)->assertOk()->assertJsonPath('data.selection_type', 'INELIGIBLE_OVERRIDE');

        $withoutOverride = $this->destinationContext([Permissions::DestinationSelectionSelect, Permissions::DestinationEvaluationsView, Permissions::DestinationEvaluationsCreate, Permissions::DestinationRequirementsManage]);
        $shared = $this->hospital($withoutOverride, 'BLOCKED', 'OPEN', 'UNAVAILABLE');
        $this->addRequirements($withoutOverride, $withoutOverride['encounter']);
        $blockedCandidate = $this->evaluate($withoutOverride, $withoutOverride['encounter'])->candidates->firstWhere('hospital_id', $shared->id);
        $this->acting($withoutOverride)->postJson('/api/v1/encounters/'.$withoutOverride['encounter']->id.'/destination-selections', ['hospital_id' => $shared->id, 'candidate_id' => $blockedCandidate->id, 'expected_destination_version' => 1, 'reason' => 'Synthetic reason', 'idempotency_key' => (string) Str::ulid()])->assertForbidden();
    }

    public function test_destination_change_preserves_history_and_rejects_stale_version(): void
    {
        $context = $this->destinationContext();
        $central = $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        $north = $this->hospital($context, 'NORTH', 'OPEN', 'AVAILABLE');
        $this->addRequirements($context, $context['encounter']);
        $evaluation = $this->evaluate($context, $context['encounter']);
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-selections';
        $first = $evaluation->candidates->firstWhere('hospital_id', $central->id);
        $second = $evaluation->candidates->firstWhere('hospital_id', $north->id);
        $this->acting($context)->postJson($url, ['hospital_id' => $central->id, 'candidate_id' => $first->id, 'expected_destination_version' => 1, 'idempotency_key' => (string) Str::ulid()])->assertOk();

        $stale = ['hospital_id' => $north->id, 'candidate_id' => $second->id, 'expected_destination_version' => 1, 'reason' => 'Synthetic destination change.', 'idempotency_key' => (string) Str::ulid()];
        $this->acting($context)->postJson($url, $stale)->assertStatus(409);
        $stale['expected_destination_version'] = 2;
        $this->acting($context)->postJson($url, $stale)->assertOk();

        $this->assertSame(2, DestinationSelection::count());
        $this->assertSame(1, DestinationSelection::whereNull('superseded_at')->count());
        $this->assertNotNull(DestinationSelection::oldest()->firstOrFail()->superseded_at);
        $this->assertSame(2, HospitalCaseNotification::where('patient_encounter_id', $context['encounter']->id)->count());
    }

    public function test_multi_patient_destinations_are_independent_and_workspace_renders(): void
    {
        $context = $this->destinationContext();
        $second = PatientEncounter::create([
            'organization_id' => $context['organization']->id,
            'emergency_case_id' => $context['case']->id,
            'encounter_index' => 2,
            'encounter_number' => $context['case']->case_number.'-P02',
            'status' => 'ACTIVE',
            'condition_level' => 'UNKNOWN',
            'allergy_status' => 'UNKNOWN',
            'medication_status' => 'UNKNOWN',
            'history_status' => 'UNKNOWN',
            'version' => 1,
            'destination_version' => 1,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $hospital = $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        foreach ([$context['encounter'], $second] as $encounter) {
            $this->addRequirements($context, $encounter);
            $candidate = $this->evaluate($context, $encounter)->candidates->firstWhere('hospital_id', $hospital->id);
            $this->acting($context)->postJson('/api/v1/encounters/'.$encounter->id.'/destination-selections', ['hospital_id' => $hospital->id, 'candidate_id' => $candidate->id, 'expected_destination_version' => 1, 'idempotency_key' => (string) Str::ulid()])->assertOk();
        }

        $this->assertSame(2, DestinationSelection::whereNull('superseded_at')->count());
        $this->acting($context)->get('/medical?case='.$context['case']->id.'&encounter='.$second->id)->assertOk()->assertSee('Medical coordination')->assertSee('Human authority');
    }

    public function test_rule_versions_are_validated_activated_and_historically_immutable(): void
    {
        $context = $this->destinationContext();
        $setResponse = $this->acting($context)->postJson('/api/v1/destination-rule-sets', ['code' => 'OPS-SECONDARY', 'name' => 'Synthetic operational rules', 'is_synthetic' => true])->assertCreated();
        $set = DestinationRuleSet::findOrFail($setResponse->json('data.id'));
        $url = '/api/v1/destination-rule-sets/'.$set->id.'/versions';

        $this->acting($context)->postJson($url, ['definition' => ['engine_version' => 'invalid']])->assertUnprocessable();
        $definition = $context['ruleVersion']->definition;
        $first = $this->acting($context)->postJson($url, ['definition' => $definition])->assertCreated();
        $this->acting($context)->postJson('/api/v1/destination-rule-versions/'.$first->json('data.id').'/activate')->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $second = $this->acting($context)->postJson($url, ['definition' => [...$definition, 'engine_version' => 'M4-TEST-2']])->assertCreated();
        $this->acting($context)->postJson('/api/v1/destination-rule-versions/'.$second->json('data.id').'/activate')->assertOk();

        $this->assertDatabaseHas('destination_rule_set_versions', ['id' => $first->json('data.id'), 'status' => 'RETIRED']);
        $this->expectException(LogicException::class);
        DestinationRuleSetVersion::findOrFail($first->json('data.id'))->update(['definition' => [...$definition, 'engine_version' => 'MUTATED']]);
    }

    public function test_manual_without_evaluation_is_explicit_and_requires_reason(): void
    {
        $context = $this->destinationContext();
        $hospital = $this->hospital($context, 'MANUAL', 'OPEN', 'AVAILABLE');
        $url = '/api/v1/encounters/'.$context['encounter']->id.'/destination-selections';
        $payload = ['hospital_id' => $hospital->id, 'expected_destination_version' => 1, 'idempotency_key' => (string) Str::ulid()];

        $this->acting($context)->postJson($url, $payload)->assertStatus(409);
        $payload['reason'] = 'Evaluation unavailable during synthetic exercise.';
        $this->acting($context)->postJson($url, $payload)->assertOk()->assertJsonPath('data.selection_type', 'MANUAL_WITHOUT_EVALUATION');
        $this->assertDatabaseHas('destination_selections', ['patient_encounter_id' => $context['encounter']->id, 'destination_evaluation_id' => null, 'selection_type' => 'MANUAL_WITHOUT_EVALUATION']);
    }

    public function test_draft_rule_version_cannot_be_used_for_evaluation(): void
    {
        $context = $this->destinationContext();
        $this->hospital($context, 'CENTRAL', 'OPEN', 'AVAILABLE');
        $this->addRequirements($context, $context['encounter']);
        $draft = DestinationRuleSetVersion::create([
            'organization_id' => $context['organization']->id,
            'destination_rule_set_id' => $context['ruleSet']->id,
            'version' => 2,
            'status' => DestinationRuleVersionStatus::Draft,
            'definition' => $context['ruleVersion']->definition,
            'created_by' => $context['user']->id,
        ]);

        $this->acting($context)->postJson('/api/v1/encounters/'.$context['encounter']->id.'/destination-evaluations', ['rule_version_id' => $draft->id, 'idempotency_key' => (string) Str::ulid()])->assertStatus(409);
        $this->assertDatabaseHas('destination_evaluations', ['destination_rule_set_version_id' => $draft->id, 'status' => 'FAILED']);
    }

    public function test_realtime_event_uses_only_case_and_dedicated_destination_channels(): void
    {
        $context = $this->destinationContext();
        $event = new DestinationStateChanged($context['organization']->id, $context['case']->id, $context['encounter']->id, 'destination.evaluation.completed', ['evaluation_id' => (string) Str::ulid(), 'status' => 'COMPLETED'], (string) Str::ulid());

        $this->assertSame(['private-case.'.$context['case']->id, 'private-destination.'.$context['encounter']->id], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));
        $this->assertArrayNotHasKey('patient', $event->broadcastWith()['data']);
    }
}
