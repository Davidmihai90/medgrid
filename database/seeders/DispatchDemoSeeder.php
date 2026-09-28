<?php

namespace Database\Seeders;

use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCrewAssignment;
use Illuminate\Database\Seeder;

class DispatchDemoSeeder extends Seeder
{
    public function run(): void
    {
        $o = Organization::where('slug', 'demo-emergency-service')->firstOrFail();
        $p = User::where('email', 'paramedic.a@medgrid.test')->firstOrFail();
        $d = User::where('email', 'driver.a@medgrid.test')->firstOrFail();
        foreach ([['AMB-21', 'Synthetic Advanced Unit'], ['AMB-34', 'Synthetic Response Unit'], ['AMB-55', 'Synthetic Reserve Unit']] as $i => $row) {
            $v = Vehicle::updateOrCreate(['organization_id' => $o->id, 'callsign' => $row[0]], ['display_name' => $row[1], 'vehicle_type' => 'AMBULANCE', 'status' => $i === 2 ? VehicleStatus::Unavailable : VehicleStatus::Available, 'is_active' => true]);
            foreach ([[$p, 'PARAMEDIC'], [$d, 'DRIVER']] as [$u,$role]) {
                VehicleCrewAssignment::firstOrCreate(['vehicle_id' => $v->id, 'user_id' => $u->id, 'ended_at' => null], ['organization_id' => $o->id, 'crew_role' => $role, 'started_at' => now()]);
            }
        }
    }
}
