<?php

namespace Database\Seeders;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\AvailabilityStatus;
use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Hospitals\Enums\NotificationStatus;
use App\Domain\Hospitals\Enums\ReceivingStatus;
use App\Domain\Hospitals\Enums\ResourceStatus;
use App\Models\CapabilityDefinition;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCapabilityAvailability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalDepartment;
use App\Models\HospitalReceivingStatus;
use App\Models\HospitalResourceDefinition;
use App\Models\HospitalResourceState;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HospitalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $network = Organization::where('slug', 'demo-hospital-network')->firstOrFail();
        $ems = Organization::where('slug', 'demo-emergency-service')->firstOrFail();
        $operator = User::where('email', 'hospital.operator.b@medgrid.test')->firstOrFail();
        $resourceManager = User::where('email', 'resources.b@medgrid.test')->firstOrFail();
        $doctor = User::where('email', 'doctor.b@medgrid.test')->firstOrFail();
        $admin = User::where('email', 'org.admin.b@medgrid.test')->firstOrFail();
        $dispatcher = User::where('email', 'dispatcher.a@medgrid.test')->firstOrFail();

        $capabilities = collect([
            'EMERGENCY_DEPARTMENT' => 'Emergency Department',
            'TRAUMA' => 'Trauma',
            'CARDIOLOGY' => 'Cardiology',
            'NEUROLOGY' => 'Neurology',
            'NEUROSURGERY' => 'Neurosurgery',
            'GENERAL_SURGERY' => 'General Surgery',
            'ORTHOPEDICS' => 'Orthopedics',
            'PEDIATRICS' => 'Pediatrics',
            'OBSTETRICS' => 'Obstetrics',
            'BURN_CARE' => 'Burn Care',
            'INTENSIVE_CARE' => 'Intensive Care',
            'CT' => 'CT',
            'MRI' => 'MRI',
            'CATH_LAB' => 'Cath Lab',
        ])->mapWithKeys(fn ($name, $code) => [$code => CapabilityDefinition::updateOrCreate(['code' => $code], ['name' => $name, 'active' => true, 'is_synthetic' => true])]);

        $resources = collect([
            'EMERGENCY_BEDS' => 'Emergency beds',
            'ICU_BEDS' => 'ICU beds',
            'PEDIATRIC_BEDS' => 'Pediatric beds',
            'ISOLATION_BEDS' => 'Isolation beds',
            'OPERATING_ROOMS' => 'Operating rooms',
            'VENTILATOR_CAPACITY' => 'Ventilator capacity',
        ])->mapWithKeys(fn ($name, $code) => [$code => HospitalResourceDefinition::updateOrCreate(['code' => $code], ['name' => $name, 'active' => true, 'is_synthetic' => true])]);

        $definitions = [
            'CENTRAL' => ['MEDGRID Central Hospital', 'Central', 'OPEN', ['CT' => 'AVAILABLE', 'NEUROSURGERY' => 'AVAILABLE', 'INTENSIVE_CARE' => 'LIMITED', 'CATH_LAB' => 'UNAVAILABLE']],
            'NORTH' => ['MEDGRID North Emergency Center', 'North', 'LIMITED', ['CT' => 'AVAILABLE', 'TRAUMA' => 'AVAILABLE', 'GENERAL_SURGERY' => 'LIMITED']],
            'PEDIATRIC' => ['MEDGRID Pediatric Institute', 'Pediatric', 'OPEN', ['PEDIATRICS' => 'AVAILABLE', 'INTENSIVE_CARE' => 'AVAILABLE', 'TRAUMA' => 'UNKNOWN']],
            'TRAUMA' => ['MEDGRID Trauma Center', 'Trauma', 'OPEN', ['TRAUMA' => 'AVAILABLE', 'NEUROSURGERY' => 'UNAVAILABLE', 'CT' => 'AVAILABLE']],
        ];

        $hospitals = [];
        foreach ($definitions as $code => [$name, $short, $receiving, $states]) {
            $hospital = Hospital::updateOrCreate(
                ['organization_id' => $network->id, 'code' => $code],
                ['name' => $name, 'short_name' => $short, 'status' => HospitalStatus::Active, 'address' => 'Synthetic '.$short.' campus', 'timezone' => 'Europe/Bucharest', 'active' => true],
            );
            $hospitals[$code] = $hospital;
            foreach ([$admin, $resourceManager] as $user) {
                DB::table('hospital_user_access')->insertOrIgnore(['id' => (string) Str::ulid(), 'organization_id' => $network->id, 'hospital_id' => $hospital->id, 'user_id' => $user->id, 'created_at' => now()]);
            }
            if (in_array($code, ['CENTRAL', 'NORTH'], true)) {
                DB::table('hospital_user_access')->insertOrIgnore(['id' => (string) Str::ulid(), 'organization_id' => $network->id, 'hospital_id' => $hospital->id, 'user_id' => $operator->id, 'created_at' => now()]);
            }
            if ($code === 'CENTRAL') {
                DB::table('hospital_user_access')->insertOrIgnore(['id' => (string) Str::ulid(), 'organization_id' => $network->id, 'hospital_id' => $hospital->id, 'user_id' => $doctor->id, 'created_at' => now()]);
            }

            $department = HospitalDepartment::updateOrCreate(['hospital_id' => $hospital->id, 'code' => 'ED'], ['organization_id' => $network->id, 'name' => 'Emergency Department', 'description' => 'Synthetic development department', 'active' => true]);
            $hospitalVersion = 1;
            foreach ($states as $capabilityCode => $state) {
                $capability = HospitalCapability::updateOrCreate(
                    ['hospital_id' => $hospital->id, 'hospital_department_id' => $department->id, 'capability_definition_id' => $capabilities[$capabilityCode]->id],
                    ['organization_id' => $network->id, 'enabled' => true, 'notes' => 'Synthetic development capability'],
                );
                HospitalCapabilityAvailability::updateOrCreate(
                    ['organization_id' => $network->id, 'idempotency_key' => 'seed-capability-'.$code.'-'.$capabilityCode],
                    ['hospital_id' => $hospital->id, 'hospital_capability_id' => $capability->id, 'status' => AvailabilityStatus::from($state), 'effective_at' => now()->subMinutes($code === 'PEDIATRIC' && $capabilityCode === 'TRAUMA' ? 70 : 5), 'reported_by' => $resourceManager->id, 'source' => AvailabilitySource::Simulation],
                );
                $hospitalVersion++;
            }
            HospitalReceivingStatus::updateOrCreate(
                ['organization_id' => $network->id, 'idempotency_key' => 'seed-receiving-'.$code],
                ['hospital_id' => $hospital->id, 'status' => ReceivingStatus::from($receiving), 'effective_at' => now()->subMinutes($code === 'NORTH' ? 32 : 4), 'reported_by' => $operator->id, 'source' => AvailabilitySource::Simulation],
            );
            $hospitalVersion++;
            $resourceCode = $code === 'PEDIATRIC' ? 'PEDIATRIC_BEDS' : 'ICU_BEDS';
            HospitalResourceState::updateOrCreate(
                ['organization_id' => $network->id, 'idempotency_key' => 'seed-resource-'.$code],
                ['hospital_id' => $hospital->id, 'hospital_resource_definition_id' => $resources[$resourceCode]->id, 'total_capacity' => $code === 'TRAUMA' ? null : 12, 'available_capacity' => $code === 'TRAUMA' ? null : ($code === 'CENTRAL' ? 3 : 6), 'status' => $code === 'TRAUMA' ? ResourceStatus::Unknown : ($code === 'CENTRAL' ? ResourceStatus::Limited : ResourceStatus::Available), 'effective_at' => now()->subMinutes(8), 'reported_by' => $resourceManager->id, 'source' => AvailabilitySource::Simulation, 'notes' => 'Synthetic operational state'],
            );
            $hospital->update(['version' => $hospitalVersion + 1]);
        }

        $case = EmergencyCase::updateOrCreate(
            ['organization_id' => $ems->id, 'idempotency_key' => 'seed-m3-incoming-case'],
            ['case_number' => 'MG-DEMO-HOSP-001', 'source' => EmergencyCaseSource::Simulation, 'status' => EmergencyCaseStatus::DestinationPending, 'priority' => EmergencyCasePriority::P1, 'incident_type' => 'Synthetic incoming emergency', 'location_text' => 'Synthetic incident location', 'summary' => 'Synthetic operational summary for Hospital Command.', 'received_at' => now()->subMinutes(18), 'version' => 1, 'created_by' => $dispatcher->id, 'updated_by' => $dispatcher->id],
        );
        HospitalCaseNotification::updateOrCreate(
            ['organization_id' => $network->id, 'idempotency_key' => 'seed-m3-incoming-notification'],
            ['hospital_id' => $hospitals['CENTRAL']->id, 'emergency_case_id' => $case->id, 'status' => NotificationStatus::Delivered, 'notified_at' => now()->subMinutes(6), 'delivered_at' => now()->subMinutes(5), 'created_by' => $dispatcher->id],
        );
    }
}
