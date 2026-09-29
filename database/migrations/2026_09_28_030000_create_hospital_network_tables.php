<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->default('Europe/Bucharest');
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'active', 'name']);
        });

        Schema::create('hospital_user_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['hospital_id', 'user_id']);
            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('hospital_departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['hospital_id', 'code']);
            $table->index(['organization_id', 'hospital_id', 'active']);
        });

        Schema::create('capability_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_synthetic')->default(true);
            $table->timestampsTz();
        });

        Schema::create('hospital_capabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('capability_definition_id')->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_until')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'hospital_id', 'enabled']);
        });

        Schema::create('hospital_capability_availabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_capability_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('UNKNOWN');
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['hospital_capability_id', 'effective_at']);
            $table->index(['hospital_id', 'effective_at']);
        });

        Schema::create('hospital_receiving_statuses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('UNKNOWN');
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['hospital_id', 'effective_at']);
        });

        Schema::create('hospital_resource_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_synthetic')->default(true);
            $table->timestampsTz();
        });

        Schema::create('hospital_resource_states', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_resource_definition_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_capacity')->nullable();
            $table->unsignedInteger('available_capacity')->nullable();
            $table->string('status')->default('UNKNOWN');
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['hospital_id', 'hospital_resource_definition_id', 'effective_at'], 'hospital_resources_current_idx');
        });

        Schema::create('hospital_operational_restrictions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('ACTIVE');
            $table->string('reason_code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['hospital_id', 'status', 'effective_at']);
        });

        Schema::create('hospital_case_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('PENDING');
            $table->timestampTz('notified_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignUlid('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampsTz();
            $table->index(['hospital_id', 'status', 'notified_at']);
            $table->index(['emergency_case_id', 'notified_at']);
        });

        DB::statement("ALTER TABLE hospitals ADD CONSTRAINT hospitals_status_check CHECK (status IN ('ACTIVE','INACTIVE','MAINTENANCE'))");
        DB::statement('ALTER TABLE hospitals ADD CONSTRAINT hospitals_coordinates_check CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))');
        DB::statement('ALTER TABLE hospital_capabilities ADD CONSTRAINT hospital_capabilities_effective_check CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until > effective_from)');
        DB::statement("CREATE UNIQUE INDEX hospital_capabilities_scope_unique ON hospital_capabilities (hospital_id, capability_definition_id, COALESCE(hospital_department_id, '00000000000000000000000000'))");
        DB::statement("ALTER TABLE hospital_capability_availabilities ADD CONSTRAINT hospital_capability_availability_status_check CHECK (status IN ('AVAILABLE','LIMITED','UNAVAILABLE','UNKNOWN'))");
        DB::statement("ALTER TABLE hospital_receiving_statuses ADD CONSTRAINT hospital_receiving_status_check CHECK (status IN ('OPEN','LIMITED','NOT_RECEIVING','UNKNOWN'))");
        DB::statement("ALTER TABLE hospital_resource_states ADD CONSTRAINT hospital_resource_status_check CHECK (status IN ('AVAILABLE','LIMITED','FULL','UNAVAILABLE','UNKNOWN'))");
        DB::statement('ALTER TABLE hospital_resource_states ADD CONSTRAINT hospital_resource_capacity_check CHECK (total_capacity IS NULL OR available_capacity IS NULL OR available_capacity <= total_capacity)');
        DB::statement("ALTER TABLE hospital_operational_restrictions ADD CONSTRAINT hospital_restriction_status_check CHECK (status IN ('ACTIVE','EXPIRED','CANCELLED'))");
        DB::statement("ALTER TABLE hospital_case_notifications ADD CONSTRAINT hospital_notification_status_check CHECK (status IN ('PENDING','DELIVERED','ACKNOWLEDGED','CANCELLED'))");
        foreach (['hospital_capability_availabilities', 'hospital_receiving_statuses', 'hospital_resource_states', 'hospital_operational_restrictions', 'hospital_case_notifications'] as $table) {
            DB::statement("CREATE UNIQUE INDEX {$table}_idempotency_unique ON {$table} (organization_id,idempotency_key) WHERE idempotency_key IS NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_case_notifications');
        Schema::dropIfExists('hospital_operational_restrictions');
        Schema::dropIfExists('hospital_resource_states');
        Schema::dropIfExists('hospital_resource_definitions');
        Schema::dropIfExists('hospital_receiving_statuses');
        Schema::dropIfExists('hospital_capability_availabilities');
        Schema::dropIfExists('hospital_capabilities');
        Schema::dropIfExists('capability_definitions');
        Schema::dropIfExists('hospital_departments');
        Schema::dropIfExists('hospital_user_access');
        Schema::dropIfExists('hospitals');
    }
};
