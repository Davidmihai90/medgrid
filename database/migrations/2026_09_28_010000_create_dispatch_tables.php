<?php

use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS emergency_case_number_seq');
        Schema::create('emergency_cases', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('case_number')->unique();
            $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $t->string('source')->default(EmergencyCaseSource::Manual->value);
            $t->string('status')->default(EmergencyCaseStatus::Received->value);
            $t->string('priority')->default(EmergencyCasePriority::Unknown->value);
            $t->string('incident_type', 120);
            $t->string('caller_name')->nullable();
            $t->string('caller_phone')->nullable();
            $t->string('location_text');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedInteger('location_accuracy_meters')->nullable();
            $t->text('summary');
            $t->text('dispatcher_notes')->nullable();
            $t->timestampTz('received_at');
            $t->timestampTz('triaged_at')->nullable();
            $t->timestampTz('assigned_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->string('idempotency_key', 100)->nullable();
            $t->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['organization_id', 'status', 'received_at']);
            $t->index(['organization_id', 'priority', 'received_at']);
        });
        Schema::create('case_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $t->string('event_type');
            $t->unsignedInteger('event_version')->default(1);
            $t->string('actor_type')->nullable();
            $t->string('actor_id', 26)->nullable();
            $t->timestampTz('occurred_at');
            $t->string('summary');
            $t->jsonb('metadata')->nullable();
            $t->ulid('correlation_id');
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['emergency_case_id', 'occurred_at']);
            $t->index(['organization_id', 'occurred_at']);
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $t->string('callsign', 40);
            $t->string('display_name');
            $t->string('registration_number', 40)->nullable();
            $t->string('vehicle_type', 80)->default('AMBULANCE');
            $t->string('status')->default(VehicleStatus::Offline->value);
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
            $t->unique(['organization_id', 'callsign']);
            $t->index(['organization_id', 'status', 'is_active']);
        });
        Schema::create('vehicle_crew_assignments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('crew_role', 80);
            $t->timestampTz('started_at');
            $t->timestampTz('ended_at')->nullable();
            $t->timestampsTz();
            $t->index(['organization_id', 'user_id', 'ended_at']);
        });
        Schema::create('case_vehicle_assignments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $t->string('status')->default(AssignmentStatus::Pending->value);
            $t->string('idempotency_key', 100)->nullable();
            $t->foreignUlid('assigned_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('assigned_at');
            $t->foreignUlid('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('delivered_at')->nullable();
            $t->foreignUlid('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('accepted_at')->nullable();
            $t->foreignUlid('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestampsTz();
            $t->index(['organization_id', 'status', 'assigned_at']);
            $t->index(['emergency_case_id', 'assigned_at']);
        });
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_source_check CHECK (source IN ('MANUAL','SIMULATION'))");
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_status_check CHECK (status IN ('RECEIVED','TRIAGED','UNIT_ASSIGNED','UNIT_ACCEPTED','EN_ROUTE_TO_SCENE','ON_SCENE','CANCELLED','CLOSED'))");
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_priority_check CHECK (priority IN ('P1','P2','P3','P4','P5','UNKNOWN'))");
        DB::statement('ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_coordinates_check CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))');
        DB::statement('CREATE UNIQUE INDEX emergency_cases_org_idempotency_unique ON emergency_cases (organization_id,idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_status_check CHECK (status IN ('OFFLINE','AVAILABLE','RESERVED','ASSIGNED','EN_ROUTE_SCENE','ON_SCENE','UNAVAILABLE'))");
        DB::statement("ALTER TABLE case_vehicle_assignments ADD CONSTRAINT case_vehicle_assignments_status_check CHECK (status IN ('PENDING','DELIVERED','ACCEPTED','CANCELLED','COMPLETED'))");
        DB::statement('CREATE UNIQUE INDEX vehicle_crew_active_unique ON vehicle_crew_assignments (vehicle_id,user_id) WHERE ended_at IS NULL');
        DB::statement("CREATE UNIQUE INDEX case_assignment_active_case_unique ON case_vehicle_assignments (emergency_case_id) WHERE status IN ('PENDING','DELIVERED','ACCEPTED')");
        DB::statement("CREATE UNIQUE INDEX case_assignment_active_vehicle_unique ON case_vehicle_assignments (vehicle_id) WHERE status IN ('PENDING','DELIVERED','ACCEPTED')");
        DB::statement('CREATE UNIQUE INDEX case_assignment_org_idempotency_unique ON case_vehicle_assignments (organization_id,idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('case_vehicle_assignments');
        Schema::dropIfExists('vehicle_crew_assignments');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('emergency_cases');
        DB::statement('DROP SEQUENCE IF EXISTS emergency_case_number_seq');
    }
};
