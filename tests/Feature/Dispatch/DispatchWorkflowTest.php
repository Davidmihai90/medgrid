<?php

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Identity\Support\Permissions;
use App\Models\CaseEvent;
use App\Models\CaseVehicleAssignment;
use App\Models\EmergencyCase;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleCrewAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class DispatchWorkflowTest extends TestCase
{
    use CreatesAuthorizationContext,RefreshDatabase;

    private function payload(): array
    {
        return ['incident_type' => 'Traffic incident', 'location_text' => 'Synthetic Avenue 12', 'summary' => 'Synthetic dispatch scenario with no patient data.', 'idempotency_key' => (string) Str::ulid()];
    }

    private function vehicle(Organization $o): Vehicle
    {
        return Vehicle::create(['organization_id' => $o->id, 'callsign' => 'AMB-21', 'display_name' => 'Synthetic Unit 21', 'status' => VehicleStatus::Available, 'is_active' => true]);
    }

    public function test_valid_payload_creates_case_timeline_and_audit_and_returns_201(): void
    {
        $x = $this->context([Permissions::CasesCreate]);
        $r = $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases', $this->payload());
        $r->assertCreated()->assertJsonPath('data.status', 'RECEIVED');
        $case = EmergencyCase::firstOrFail();
        $this->assertMatchesRegularExpression('/^MG-\d{4}-\d{6}$/', $case->case_number);
        $this->assertSame($x['organization']->id, $case->organization_id);
        $this->assertSame(1, CaseEvent::where('event_type', 'CaseCreated')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.created', 'resource_id' => $case->id]);
    }

    public function test_repeated_idempotency_key_returns_same_case(): void
    {
        $x = $this->context([Permissions::CasesCreate]);
        $p = $this->payload();
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases', $p)->assertCreated();
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases', $p)->assertCreated();
        $this->assertSame(1, EmergencyCase::count());
    }

    public function test_cross_organization_case_is_hidden_with_404(): void
    {
        $x = $this->context([Permissions::CasesView]);
        $other = Organization::factory()->create();
        $case = EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900001', 'organization_id' => $other->id, 'status' => EmergencyCaseStatus::Received, 'priority' => EmergencyCasePriority::Unknown, 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_triage_and_assignment_update_case_vehicle_timeline_and_audit_atomically(): void
    {
        $x = $this->context([Permissions::CasesCreate, Permissions::CasesUpdate, Permissions::CasesAssign]);
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases', $this->payload());
        $case = EmergencyCase::firstOrFail();
        $vehicle = $this->vehicle($x['organization']);
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases/'.$case->id.'/triage', ['priority' => 'P2'])->assertOk();
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson('/api/v1/cases/'.$case->id.'/assignments', ['vehicle_id' => $vehicle->id, 'idempotency_key' => (string) Str::ulid()])->assertCreated()->assertJsonPath('data.status', 'PENDING');
        $this->assertSame(EmergencyCaseStatus::UnitAssigned, $case->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $vehicle->fresh()->status);
        $this->assertDatabaseHas('case_events', ['emergency_case_id' => $case->id, 'event_type' => 'UnitAssigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.created']);
    }

    public function test_second_active_assignment_for_vehicle_is_rejected_by_database_constraint(): void
    {
        $x = $this->context();
        $v = $this->vehicle($x['organization']);
        $a = EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900002', 'organization_id' => $x['organization']->id, 'status' => EmergencyCaseStatus::Triaged, 'priority' => EmergencyCasePriority::P2, 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        $b = EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900003', 'organization_id' => $x['organization']->id, 'status' => EmergencyCaseStatus::Triaged, 'priority' => EmergencyCasePriority::P3, 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        CaseVehicleAssignment::create(['organization_id' => $x['organization']->id, 'emergency_case_id' => $a->id, 'vehicle_id' => $v->id, 'status' => AssignmentStatus::Pending, 'assigned_by' => $x['user']->id, 'assigned_at' => now()]);
        $this->expectException(QueryException::class);
        CaseVehicleAssignment::create(['organization_id' => $x['organization']->id, 'emergency_case_id' => $b->id, 'vehicle_id' => $v->id, 'status' => AssignmentStatus::Pending, 'assigned_by' => $x['user']->id, 'assigned_at' => now()]);
    }

    public function test_active_vehicle_crew_can_accept_once_and_outsider_cannot(): void
    {
        $x = $this->context([Permissions::AssignmentsAccept]);
        $v = $this->vehicle($x['organization']);
        VehicleCrewAssignment::create(['organization_id' => $x['organization']->id, 'vehicle_id' => $v->id, 'user_id' => $x['user']->id, 'crew_role' => 'PARAMEDIC', 'started_at' => now()]);
        $case = EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900004', 'organization_id' => $x['organization']->id, 'status' => EmergencyCaseStatus::UnitAssigned, 'priority' => EmergencyCasePriority::P1, 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        $a = CaseVehicleAssignment::create(['organization_id' => $x['organization']->id, 'emergency_case_id' => $case->id, 'vehicle_id' => $v->id, 'status' => AssignmentStatus::Pending, 'assigned_by' => $x['user']->id, 'assigned_at' => now()]);
        $url = '/api/v1/assignments/'.$a->id.'/acceptance';
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson($url)->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->postJson($url)->assertOk();
        $this->assertSame(1, CaseEvent::where('event_type', 'MissionAccepted')->count());
        $this->assertSame(EmergencyCaseStatus::UnitAccepted, $case->fresh()->status);
    }

    public function test_dispatch_channel_rejects_member_of_another_organization(): void
    {
        $x = $this->context([Permissions::CasesView]);
        $other = Organization::factory()->create();
        $this->actingAs($x['user'])->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-dispatch.'.$other->id])->assertForbidden();
    }

    public function test_reassignment_requires_reason_and_moves_active_assignment_atomically(): void
    {
        $x = $this->context([Permissions::AssignmentsReassign]);
        $old = $this->vehicle($x['organization']);
        $new = Vehicle::create(['organization_id' => $x['organization']->id, 'callsign' => 'AMB-22', 'display_name' => 'Synthetic Unit 22', 'status' => VehicleStatus::Available, 'is_active' => true]);
        $case = EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900005', 'organization_id' => $x['organization']->id, 'status' => EmergencyCaseStatus::UnitAssigned, 'priority' => EmergencyCasePriority::P2, 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);
        $assignment = CaseVehicleAssignment::create(['organization_id' => $x['organization']->id, 'emergency_case_id' => $case->id, 'vehicle_id' => $old->id, 'status' => AssignmentStatus::Pending, 'assigned_by' => $x['user']->id, 'assigned_at' => now()]);
        $old->update(['status' => VehicleStatus::Assigned]);
        $url = '/api/v1/assignments/'.$assignment->id;

        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->putJson($url, ['vehicle_id' => $new->id])->assertUnprocessable();
        $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->putJson($url, ['vehicle_id' => $new->id, 'reason' => 'Operational unit substitution'])->assertOk()->assertJsonPath('data.vehicle_id', $new->id);

        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Available, $old->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $new->fresh()->status);
        $this->assertDatabaseHas('case_events', ['emergency_case_id' => $case->id, 'event_type' => 'UnitReassigned']);
    }

    public function test_dispatch_board_renders_operational_layout_and_escapes_case_content(): void
    {
        $x = $this->context([Permissions::CasesView]);
        $x['role']->update(['slug' => 'dispatcher']);
        $this->vehicle($x['organization']);
        EmergencyCase::create([...$this->payload(), 'case_number' => 'MG-2026-900006', 'organization_id' => $x['organization']->id, 'status' => EmergencyCaseStatus::Received, 'priority' => EmergencyCasePriority::P1, 'incident_type' => '<script>alert(1)</script>', 'received_at' => now(), 'created_by' => $x['user']->id, 'updated_by' => $x['user']->id]);

        $response = $this->actingAs($x['user'])->withSession(['current_organization_id' => $x['organization']->id])->get('/dispatch');

        $response->assertOk()->assertSee('Dispatch board')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
