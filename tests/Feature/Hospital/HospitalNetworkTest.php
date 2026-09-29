<?php

namespace Tests\Feature\Hospital;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Hospitals\Enums\NotificationStatus;
use App\Domain\Hospitals\Exceptions\HospitalStateConflict;
use App\Domain\Hospitals\Services\HospitalOperationalSnapshotService;
use App\Domain\Hospitals\Services\HospitalOperationsService;
use App\Domain\Identity\Support\Permissions;
use App\Events\HospitalStateChanged;
use App\Models\CapabilityDefinition;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalDepartment;
use App\Models\HospitalResourceDefinition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class HospitalNetworkTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    private function hospital(array $context, User|false|null $user = null, array $attributes = []): Hospital
    {
        $hospital = Hospital::create([
            'organization_id' => $context['organization']->id,
            'code' => $attributes['code'] ?? 'CENTRAL',
            'name' => $attributes['name'] ?? 'Synthetic Central Hospital',
            'status' => HospitalStatus::Active,
            'address' => $attributes['address'] ?? 'Synthetic Campus',
            'timezone' => 'Europe/Bucharest',
            'active' => true,
            'version' => 1,
        ]);
        if ($user !== false) {
            $hospital->users()->attach(($user ?? $context['user'])->id, ['id' => (string) Str::ulid(), 'organization_id' => $context['organization']->id]);
        }

        return $hospital;
    }

    private function capability(Hospital $hospital): HospitalCapability
    {
        $definition = CapabilityDefinition::create(['code' => 'CT', 'name' => 'CT', 'active' => true, 'is_synthetic' => true]);

        return HospitalCapability::create(['organization_id' => $hospital->organization_id, 'hospital_id' => $hospital->id, 'capability_definition_id' => $definition->id, 'enabled' => true]);
    }

    private function operationalPayload(Hospital $hospital, string $status = 'OPEN'): array
    {
        return ['status' => $status, 'effective_at' => now()->utc()->toIso8601String(), 'source' => 'MANUAL', 'expected_version' => $hospital->version, 'idempotency_key' => (string) Str::ulid()];
    }

    public function test_authorized_hospital_is_visible_but_same_org_unassigned_hospital_is_hidden(): void
    {
        $context = $this->context([Permissions::HospitalsView]);
        $visible = $this->hospital($context);
        $hidden = $this->hospital($context, false, ['code' => 'NORTH', 'name' => 'Synthetic North']);

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->getJson('/api/v1/hospitals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->getJson('/api/v1/hospitals/'.$hidden->id)->assertNotFound();
    }

    public function test_cross_organization_hospital_is_hidden(): void
    {
        $context = $this->context([Permissions::HospitalsView]);
        $other = $this->context([Permissions::HospitalsView]);
        $hospital = $this->hospital($other);

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->getJson('/api/v1/hospitals/'.$hospital->id)->assertNotFound();
    }

    public function test_receiving_updates_preserve_history_and_calculate_freshness(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $url = '/api/v1/hospitals/'.$hospital->id.'/receiving-status';

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson($url, $this->operationalPayload($hospital, 'OPEN'))->assertCreated()->assertJsonPath('meta.hospital_version', 2);
        $hospital->refresh();
        $payload = $this->operationalPayload($hospital, 'LIMITED');
        $payload['effective_at'] = now()->subMinutes(20)->utc()->toIso8601String();
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson($url, $payload)->assertCreated();

        $this->assertSame(2, $hospital->receivingStatuses()->count());
        $snapshot = app(HospitalOperationalSnapshotService::class)->for($hospital->fresh(), CarbonImmutable::now('UTC'));
        $this->assertSame('OPEN', $snapshot['receiving']['value']);
        $this->assertSame('FRESH', $snapshot['receiving']['freshness']);
    }

    public function test_capability_and_availability_are_separate_and_expiry_becomes_unknown(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $capability = $this->capability($hospital);
        $before = app(HospitalOperationalSnapshotService::class)->for($hospital);
        $this->assertTrue($before['capabilities'][0]['enabled']);
        $this->assertSame('UNKNOWN', $before['capabilities'][0]['availability']['value']);

        $payload = $this->operationalPayload($hospital, 'AVAILABLE');
        $payload['expires_at'] = now()->addMinute()->utc()->toIso8601String();
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospital-capabilities/'.$capability->id.'/availability', $payload)->assertCreated();

        $snapshot = app(HospitalOperationalSnapshotService::class)->for($hospital->fresh(), CarbonImmutable::now('UTC')->addMinutes(2));
        $this->assertSame('UNKNOWN', $snapshot['capabilities'][0]['availability']['value']);
        $this->assertTrue($snapshot['capabilities'][0]['availability']['expired']);
        $this->assertSame(1, $capability->availabilities()->count());
    }

    public function test_stale_status_is_explicit(): void
    {
        config(['medgrid.hospital_freshness.fresh_minutes' => 5, 'medgrid.hospital_freshness.aging_minutes' => 10]);
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $payload = $this->operationalPayload($hospital);
        $payload['effective_at'] = now()->subMinutes(30)->utc()->toIso8601String();

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/receiving-status', $payload)->assertCreated();

        $snapshot = app(HospitalOperationalSnapshotService::class)->for($hospital->fresh());
        $this->assertSame('OPEN', $snapshot['receiving']['value']);
        $this->assertSame('STALE', $snapshot['receiving']['freshness']);
    }

    public function test_resource_zero_is_distinct_from_unknown_capacity(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalResourcesUpdate]);
        $hospital = $this->hospital($context);
        $definition = HospitalResourceDefinition::create(['code' => 'ICU_BEDS', 'name' => 'ICU beds', 'active' => true, 'is_synthetic' => true]);
        $url = '/api/v1/hospitals/'.$hospital->id.'/resources/'.$definition->id.'/state';

        $zero = $this->operationalPayload($hospital, 'FULL') + ['total_capacity' => 10, 'available_capacity' => 0];
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url, $zero)->assertCreated();
        $hospital->refresh();
        $unknown = $this->operationalPayload($hospital, 'UNKNOWN');
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url, $unknown)->assertCreated();

        $states = $hospital->resourceStates()->oldest('created_at')->get();
        $this->assertSame(0, $states[0]->available_capacity);
        $this->assertNull($states[1]->available_capacity);
    }

    public function test_stale_expected_version_returns_hospital_conflict_without_overwrite(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $url = '/api/v1/hospitals/'.$hospital->id.'/receiving-status';
        $first = $this->operationalPayload($hospital, 'OPEN');

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url, $first)->assertCreated();
        $second = $this->operationalPayload($hospital, 'NOT_RECEIVING');
        $second['expected_version'] = 1;
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson($url, $second)->assertStatus(409)->assertJsonPath('error.code', 'HOSPITAL_STATE_CONFLICT');
        $this->assertSame(1, $hospital->receivingStatuses()->count());
    }

    public function test_retried_operational_update_is_idempotent(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $payload = $this->operationalPayload($hospital, 'OPEN');
        $url = '/api/v1/hospitals/'.$hospital->id.'/receiving-status';

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url, $payload)->assertCreated();
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url, $payload)->assertCreated();
        $this->assertSame(1, $hospital->receivingStatuses()->count());
        $this->assertSame(2, $hospital->fresh()->version);
    }

    public function test_duplicate_capability_scope_is_prevented(): void
    {
        $context = $this->context();
        $hospital = $this->hospital($context);
        $capability = $this->capability($hospital);

        $this->expectException(QueryException::class);
        HospitalCapability::create(['organization_id' => $hospital->organization_id, 'hospital_id' => $hospital->id, 'capability_definition_id' => $capability->capability_definition_id, 'enabled' => true]);
    }

    public function test_incoming_acknowledgement_is_idempotent_and_only_records_receipt(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalIncomingView, Permissions::HospitalIncomingAcknowledge]);
        $hospital = $this->hospital($context);
        $case = EmergencyCase::create(['case_number' => 'MG-2026-HOSP01', 'organization_id' => $context['organization']->id, 'source' => EmergencyCaseSource::Simulation, 'status' => EmergencyCaseStatus::DestinationPending, 'priority' => EmergencyCasePriority::P1, 'incident_type' => 'Synthetic incident', 'location_text' => 'Synthetic location', 'summary' => 'Synthetic summary', 'received_at' => now(), 'version' => 1, 'created_by' => $context['user']->id, 'updated_by' => $context['user']->id]);
        $notification = HospitalCaseNotification::create(['organization_id' => $hospital->organization_id, 'hospital_id' => $hospital->id, 'emergency_case_id' => $case->id, 'status' => NotificationStatus::Delivered, 'notified_at' => now(), 'delivered_at' => now(), 'created_by' => $context['user']->id]);
        $url = '/api/v1/hospital-notifications/'.$notification->id.'/acknowledge';

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url)->assertOk()->assertJsonPath('data.status', 'ACKNOWLEDGED');
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->postJson($url)->assertOk();
        $this->assertSame(EmergencyCaseStatus::DestinationPending, $case->fresh()->status);
        $this->assertSame(1, $case->events()->where('event_type', 'HospitalNotificationAcknowledged')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.notification.acknowledged', 'resource_id' => $notification->id]);
    }

    public function test_hospital_channel_requires_explicit_access_and_payload_is_minimal(): void
    {
        $context = $this->context([Permissions::HospitalsView]);
        $hospital = $this->hospital($context);
        $outsider = User::factory()->create();

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-hospital.'.$hospital->id])->assertOk();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-hospital.'.$hospital->id])->assertForbidden();

        $event = new HospitalStateChanged($hospital->id, $hospital->organization_id, 'hospital.receiving.changed', ['status' => 'OPEN'], (string) Str::ulid());
        $payload = $event->broadcastWith();
        $this->assertSame(['status' => 'OPEN'], $payload['data']);
        $this->assertArrayNotHasKey('patient', $payload);
        $this->assertSame('private-hospital.'.$hospital->id, $event->broadcastOn()[0]->name);
    }

    public function test_hospital_command_renders_truthful_states_and_escapes_text(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityView, Permissions::HospitalResourcesView, Permissions::HospitalIncomingView, Permissions::HospitalAvailabilityUpdate]);
        $context['role']->update(['slug' => 'hospital-operator']);
        $hospital = $this->hospital($context, null, ['address' => '<script>alert(1)</script>']);
        $this->actingAs($context['user'])
            ->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/receiving-status', $this->operationalPayload($hospital))
            ->assertCreated();

        $response = $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])->get('/hospital?hospital='.$hospital->id);

        $response->assertOk()->assertSee('Hospital Command')->assertSee('UNKNOWN')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_hospital_creation_is_authorized_and_code_is_unique_per_organization(): void
    {
        $context = $this->context([Permissions::HospitalsManage, Permissions::HospitalsView]);
        $payload = ['code' => 'WEST', 'name' => 'Synthetic West Hospital', 'status' => 'ACTIVE', 'address' => 'Synthetic West Campus', 'timezone' => 'Europe/Bucharest', 'active' => true];

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals', $payload)->assertCreated()->assertJsonPath('data.code', 'WEST');
        $hospital = Hospital::where('code', 'WEST')->firstOrFail();
        $this->assertTrue($hospital->users()->whereKey($context['user']->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.created', 'resource_id' => $hospital->id]);

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');

        $unprivileged = $this->context([Permissions::HospitalsView]);
        $this->actingAs($unprivileged['user'])->withSession(['current_organization_id' => $unprivileged['organization']->id])
            ->postJson('/api/v1/hospitals', [...$payload, 'code' => 'DENIED'])->assertForbidden();
    }

    public function test_inactive_hospital_is_not_available_as_operational_context(): void
    {
        $context = $this->context([Permissions::HospitalsView]);
        $hospital = $this->hospital($context);
        $hospital->update(['status' => HospitalStatus::Inactive]);

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->getJson('/api/v1/hospitals/'.$hospital->id)->assertNotFound();
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->getJson('/api/v1/hospitals')->assertJsonCount(0, 'data');
    }

    public function test_departments_and_capabilities_are_configured_with_hospital_scope_and_versioning(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalsManage, Permissions::HospitalDepartmentsManage, Permissions::HospitalCapabilitiesManage]);
        $hospital = $this->hospital($context);
        $other = $this->hospital($context, null, ['code' => 'OTHER', 'name' => 'Synthetic Other']);
        $definition = CapabilityDefinition::create(['code' => 'MRI', 'name' => 'MRI', 'active' => true, 'is_synthetic' => true]);

        $departmentResponse = $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/departments', ['code' => 'RAD', 'name' => 'Radiology', 'active' => true])
            ->assertCreated();
        $department = HospitalDepartment::findOrFail($departmentResponse->json('data.id'));
        $hospital->refresh();

        $capabilityResponse = $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/capabilities', ['capability_definition_id' => $definition->id, 'hospital_department_id' => $department->id, 'enabled' => true, 'expected_version' => $hospital->version])
            ->assertCreated()->assertJsonPath('data.enabled', true);
        $capability = HospitalCapability::findOrFail($capabilityResponse->json('data.id'));
        $hospital->refresh();

        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->putJson('/api/v1/hospital-capabilities/'.$capability->id, ['enabled' => false, 'expected_version' => $hospital->version])
            ->assertOk()->assertJsonPath('data.enabled', false);
        $this->assertSame(0, $capability->availabilities()->count());

        $foreignDepartment = HospitalDepartment::create(['organization_id' => $other->organization_id, 'hospital_id' => $other->id, 'code' => 'ED', 'name' => 'Emergency', 'active' => true]);
        $secondDefinition = CapabilityDefinition::create(['code' => 'CT-SECOND', 'name' => 'CT second', 'active' => true, 'is_synthetic' => true]);
        $this->actingAs($context['user'])->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/capabilities', ['capability_definition_id' => $secondDefinition->id, 'hospital_department_id' => $foreignDepartment->id, 'enabled' => true, 'expected_version' => $hospital->fresh()->version])
            ->assertStatus(409)->assertJsonPath('error.code', 'HOSPITAL_STATE_CONFLICT');
    }

    public function test_cross_organization_operational_mutation_is_hidden(): void
    {
        $context = $this->context([Permissions::HospitalAvailabilityUpdate]);
        $other = $this->context([Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($other);

        $this->actingAs($context['user'])
            ->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/receiving-status', $this->operationalPayload($hospital))
            ->assertNotFound();

        $this->assertSame(0, $hospital->receivingStatuses()->count());
    }

    public function test_restrictions_are_audited_and_snapshot_projection_is_deterministic(): void
    {
        $context = $this->context([Permissions::HospitalsView, Permissions::HospitalAvailabilityUpdate]);
        $hospital = $this->hospital($context);
        $payload = [
            'reason_code' => 'CAPACITY',
            'title' => 'Synthetic temporary restriction',
            'description' => 'Synthetic operational test data.',
            'effective_at' => now()->subMinute()->utc()->toIso8601String(),
            'expires_at' => now()->addHour()->utc()->toIso8601String(),
            'source' => 'SIMULATION',
            'expected_version' => $hospital->version,
            'idempotency_key' => (string) Str::ulid(),
        ];

        $this->actingAs($context['user'])
            ->withSession(['current_organization_id' => $context['organization']->id])
            ->postJson('/api/v1/hospitals/'.$hospital->id.'/restrictions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Synthetic temporary restriction');

        $reference = CarbonImmutable::now('UTC');
        $first = app(HospitalOperationalSnapshotService::class)->for($hospital->fresh(), $reference);
        $second = app(HospitalOperationalSnapshotService::class)->for($hospital->fresh(), $reference);

        $this->assertSame($first, $second);
        $this->assertSame('Synthetic temporary restriction', $first['restrictions'][0]['title']);
        $this->assertNotNull($first['last_updated_at']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'hospital.restriction.created',
            'resource_id' => $first['restrictions'][0]['id'],
        ]);
    }

    public function test_notification_creation_and_delivery_are_idempotent_and_audited(): void
    {
        $context = $this->context();
        $hospital = $this->hospital($context);
        $case = EmergencyCase::create([
            'case_number' => 'MG-2026-HOSP02',
            'organization_id' => $context['organization']->id,
            'source' => EmergencyCaseSource::Simulation,
            'status' => EmergencyCaseStatus::DestinationPending,
            'priority' => EmergencyCasePriority::P2,
            'incident_type' => 'Synthetic incoming case',
            'location_text' => 'Synthetic location',
            'summary' => 'Synthetic summary',
            'received_at' => now(),
            'version' => 1,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $operations = app(HospitalOperationsService::class);
        $key = (string) Str::ulid();

        $notification = $operations->notify($hospital, $case, $context['user'], null, $key);
        $retry = $operations->notify($hospital, $case, $context['user'], null, $key);
        $delivered = $operations->markDelivered($notification, $context['user']);
        $operations->markDelivered($delivered, $context['user']);

        $this->assertSame($notification->id, $retry->id);
        $this->assertSame(NotificationStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame(1, $case->events()->where('event_type', 'HospitalNotified')->count());
        $this->assertSame(1, HospitalCaseNotification::where('idempotency_key', $key)->count());
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.notification.created', 'resource_id' => $notification->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.notification.delivered', 'resource_id' => $notification->id]);
    }

    public function test_notification_rejects_cross_organization_case(): void
    {
        $context = $this->context();
        $other = $this->context();
        $hospital = $this->hospital($context);
        $case = EmergencyCase::create([
            'case_number' => 'MG-2026-HOSP03',
            'organization_id' => $other['organization']->id,
            'source' => EmergencyCaseSource::Simulation,
            'status' => EmergencyCaseStatus::DestinationPending,
            'priority' => EmergencyCasePriority::P3,
            'incident_type' => 'Synthetic cross-tenant case',
            'location_text' => 'Synthetic location',
            'summary' => 'Synthetic summary',
            'received_at' => now(),
            'version' => 1,
            'created_by' => $other['user']->id,
            'updated_by' => $other['user']->id,
        ]);

        $this->expectException(HospitalStateConflict::class);

        app(HospitalOperationsService::class)->notify(
            $hospital,
            $case,
            $context['user'],
            null,
            (string) Str::ulid(),
        );
    }
}
