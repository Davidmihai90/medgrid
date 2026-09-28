<?php

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Identity\Support\Permissions;
use App\Models\CaseVehicleAssignment;
use App\Models\EmergencyCase;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    private function dispatchContext(array $permissions): array
    {
        $context = $this->context($permissions);
        $context['role']->update(['slug' => 'dispatcher']);

        return $context;
    }

    private function vehicle(Organization $organization, string $callsign, VehicleStatus $status = VehicleStatus::Available): Vehicle
    {
        return Vehicle::create([
            'organization_id' => $organization->id,
            'callsign' => $callsign,
            'display_name' => 'Synthetic '.$callsign,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function activeAssignment(array $context, string $callsign = 'AMB-31'): array
    {
        $vehicle = $this->vehicle($context['organization'], $callsign, VehicleStatus::Assigned);
        $case = EmergencyCase::create([
            'organization_id' => $context['organization']->id,
            'case_number' => 'MG-2026-910001',
            'incident_type' => 'Synthetic dispatch exercise',
            'location_text' => 'Training Avenue 31',
            'summary' => 'Synthetic operational data with no patient information.',
            'status' => EmergencyCaseStatus::UnitAssigned,
            'priority' => EmergencyCasePriority::P2,
            'received_at' => now(),
            'assigned_at' => now(),
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $assignment = CaseVehicleAssignment::create([
            'organization_id' => $context['organization']->id,
            'emergency_case_id' => $case->id,
            'vehicle_id' => $vehicle->id,
            'status' => AssignmentStatus::Pending,
            'assigned_by' => $context['user']->id,
            'assigned_at' => now(),
        ]);

        return compact('case', 'vehicle', 'assignment');
    }

    private function asUser(array $context): static
    {
        return $this->actingAs($context['user'])
            ->withSession(['current_organization_id' => $context['organization']->id]);
    }

    public function test_authorized_dispatcher_can_cancel_with_reason_and_preserve_operational_history(): void
    {
        $context = $this->dispatchContext([Permissions::AssignmentsCancel]);
        ['case' => $case, 'vehicle' => $vehicle, 'assignment' => $assignment] = $this->activeAssignment($context);

        $response = $this->asUser($context)->delete(route('dispatch.assignments.destroy', $assignment), [
            'reason' => 'Unit required for a higher priority incident',
            'assignment_action' => 'cancel',
        ]);

        $response->assertRedirect(route('area.dispatch', ['case' => $case->id]))
            ->assertSessionHas('status');
        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
        $this->assertSame('Unit required for a higher priority incident', $assignment->fresh()->cancellation_reason);
        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
        $this->assertSame(EmergencyCaseStatus::Triaged, $case->fresh()->status);
        $this->assertDatabaseHas('case_events', [
            'emergency_case_id' => $case->id,
            'event_type' => 'UnitAssignmentCancelled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assignment.cancelled',
            'resource_id' => $assignment->id,
        ]);
    }

    public function test_cancellation_requires_permission(): void
    {
        $context = $this->dispatchContext([]);
        ['vehicle' => $vehicle, 'assignment' => $assignment] = $this->activeAssignment($context);

        $this->asUser($context)->delete(route('dispatch.assignments.destroy', $assignment), [
            'reason' => 'Operational cancellation',
        ])->assertForbidden();

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $vehicle->fresh()->status);
    }

    public function test_cancellation_requires_reason_without_changing_state(): void
    {
        $context = $this->dispatchContext([Permissions::AssignmentsCancel]);
        ['vehicle' => $vehicle, 'assignment' => $assignment] = $this->activeAssignment($context);

        $this->asUser($context)->from(route('area.dispatch'))
            ->delete(route('dispatch.assignments.destroy', $assignment), [
                'assignment_action' => 'cancel',
            ])
            ->assertRedirect(route('area.dispatch'))
            ->assertSessionHasErrors('reason');

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $vehicle->fresh()->status);
    }

    public function test_authorized_dispatcher_can_reassign_atomically_and_preserve_previous_assignment(): void
    {
        $context = $this->dispatchContext([Permissions::AssignmentsReassign]);
        ['case' => $case, 'vehicle' => $oldVehicle, 'assignment' => $assignment] = $this->activeAssignment($context);
        $newVehicle = $this->vehicle($context['organization'], 'AMB-32');

        $response = $this->asUser($context)->put(route('dispatch.assignments.update', $assignment), [
            'vehicle_id' => $newVehicle->id,
            'reason' => 'Closer unit became available',
            'assignment_action' => 'reassign',
        ]);

        $response->assertRedirect(route('area.dispatch', ['case' => $case->id]))
            ->assertSessionHas('status', 'Unit reassigned to AMB-32.');
        $newAssignment = CaseVehicleAssignment::whereKeyNot($assignment->id)->sole();
        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
        $this->assertSame('Closer unit became available', $assignment->fresh()->cancellation_reason);
        $this->assertSame(AssignmentStatus::Pending, $newAssignment->status);
        $this->assertSame($newVehicle->id, $newAssignment->vehicle_id);
        $this->assertSame(VehicleStatus::Available, $oldVehicle->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $newVehicle->fresh()->status);
        $this->assertSame(EmergencyCaseStatus::UnitAssigned, $case->fresh()->status);
        $this->assertDatabaseHas('case_events', [
            'emergency_case_id' => $case->id,
            'event_type' => 'UnitReassigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assignment.reassigned',
            'resource_id' => $newAssignment->id,
        ]);
    }

    public function test_reassignment_requires_permission(): void
    {
        $context = $this->dispatchContext([]);
        ['vehicle' => $oldVehicle, 'assignment' => $assignment] = $this->activeAssignment($context);
        $newVehicle = $this->vehicle($context['organization'], 'AMB-32');

        $this->asUser($context)->put(route('dispatch.assignments.update', $assignment), [
            'vehicle_id' => $newVehicle->id,
            'reason' => 'Operational reassignment',
        ])->assertForbidden();

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $oldVehicle->fresh()->status);
        $this->assertSame(VehicleStatus::Available, $newVehicle->fresh()->status);
    }

    public function test_reassignment_requires_reason_without_changing_state(): void
    {
        $context = $this->dispatchContext([Permissions::AssignmentsReassign]);
        ['vehicle' => $oldVehicle, 'assignment' => $assignment] = $this->activeAssignment($context);
        $newVehicle = $this->vehicle($context['organization'], 'AMB-32');

        $this->asUser($context)->from(route('area.dispatch'))
            ->put(route('dispatch.assignments.update', $assignment), [
                'vehicle_id' => $newVehicle->id,
                'assignment_action' => 'reassign',
            ])
            ->assertRedirect(route('area.dispatch'))
            ->assertSessionHasErrors('reason');

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $oldVehicle->fresh()->status);
        $this->assertSame(VehicleStatus::Available, $newVehicle->fresh()->status);
    }

    public function test_unavailable_reassignment_target_returns_useful_conflict_and_preserves_state(): void
    {
        $context = $this->dispatchContext([Permissions::AssignmentsReassign]);
        ['vehicle' => $oldVehicle, 'assignment' => $assignment] = $this->activeAssignment($context);
        $newVehicle = $this->vehicle($context['organization'], 'AMB-32', VehicleStatus::Assigned);

        $this->asUser($context)->from(route('area.dispatch', ['case' => $assignment->emergency_case_id]))
            ->put(route('dispatch.assignments.update', $assignment), [
                'vehicle_id' => $newVehicle->id,
                'reason' => 'Closer unit requested',
                'assignment_action' => 'reassign',
            ])
            ->assertRedirect(route('area.dispatch', ['case' => $assignment->emergency_case_id]))
            ->assertSessionHasErrors([
                'dispatch' => 'Unit AMB-32 is no longer available. Select another unit.',
            ]);

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $oldVehicle->fresh()->status);
        $this->assertSame(VehicleStatus::Assigned, $newVehicle->fresh()->status);
        $this->assertSame(1, CaseVehicleAssignment::count());
    }

    public function test_assignment_from_another_organization_is_hidden(): void
    {
        $context = $this->dispatchContext([
            Permissions::AssignmentsCancel,
            Permissions::AssignmentsReassign,
        ]);
        $other = $this->context([]);
        ['assignment' => $assignment] = $this->activeAssignment($other);
        $localVehicle = $this->vehicle($context['organization'], 'AMB-32');

        $this->asUser($context)->delete(route('dispatch.assignments.destroy', $assignment), [
            'reason' => 'Unauthorized cross organization attempt',
        ])->assertNotFound();

        $this->asUser($context)->put(route('dispatch.assignments.update', $assignment), [
            'vehicle_id' => $localVehicle->id,
            'reason' => 'Unauthorized cross organization attempt',
        ])->assertNotFound();

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
    }

    public function test_dispatch_board_only_exposes_assignment_actions_granted_to_user(): void
    {
        $context = $this->dispatchContext([
            Permissions::CasesView,
            Permissions::AssignmentsCancel,
        ]);
        ['assignment' => $assignment] = $this->activeAssignment($context);

        $this->asUser($context)
            ->get(route('area.dispatch', ['case' => $assignment->emergency_case_id]))
            ->assertOk()
            ->assertSee('Cancel assignment')
            ->assertDontSee('Confirm reassignment');
    }
}
