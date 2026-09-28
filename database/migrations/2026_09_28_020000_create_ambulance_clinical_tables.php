<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE emergency_cases DROP CONSTRAINT emergency_cases_status_check');
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_status_check CHECK (status IN ('RECEIVED','TRIAGED','UNIT_ASSIGNED','UNIT_ACCEPTED','EN_ROUTE_TO_SCENE','ON_SCENE','PATIENT_CONTACT','ASSESSMENT','DESTINATION_PENDING','CANCELLED','CLOSED'))");

        Schema::create('patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->string('identity_status');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('estimated_age_min')->nullable();
            $table->unsignedSmallInteger('estimated_age_max')->nullable();
            $table->string('sex', 32)->nullable();
            $table->text('national_identifier')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['organization_id', 'identity_status']);
        });

        Schema::create('patient_encounters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('encounter_index');
            $table->string('encounter_number')->unique();
            $table->string('status')->default('ACTIVE');
            $table->string('condition_level')->default('UNKNOWN');
            $table->string('allergy_status')->default('UNKNOWN');
            $table->string('medication_status')->default('UNKNOWN');
            $table->string('history_status')->default('UNKNOWN');
            $table->timestampTz('contact_at')->nullable();
            $table->timestampTz('assessment_started_at')->nullable();
            $table->timestampTz('assessment_completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['emergency_case_id', 'encounter_index']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('vital_observations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('value_numeric', 12, 4)->nullable();
            $table->decimal('secondary_value_numeric', 12, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->string('unit', 32)->nullable();
            $table->unsignedSmallInteger('gcs_eye')->nullable();
            $table->unsignedSmallInteger('gcs_verbal')->nullable();
            $table->unsignedSmallInteger('gcs_motor')->nullable();
            $table->unsignedSmallInteger('gcs_total')->nullable();
            $table->boolean('gcs_total_derived')->default(false);
            $table->timestampTz('measured_at');
            $table->timestampTz('recorded_at');
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('source')->default('MANUAL');
            $table->string('device_identifier')->nullable();
            $table->ulid('operation_id')->nullable();
            $table->ulid('supersedes_observation_id')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->foreignUlid('superseded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['organization_id', 'operation_id']);
            $table->unique('supersedes_observation_id');
            $table->index(['patient_encounter_id', 'type', 'measured_at']);
        });

        Schema::table('vital_observations', function (Blueprint $table) {
            $table->foreign('supersedes_observation_id')->references('id')->on('vital_observations')->restrictOnDelete();
        });

        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_synthetic')->default(true);
            $table->timestampsTz();
            $table->unique(['organization_id', 'code']);
        });
        Schema::create('assessment_template_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_template_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->jsonb('definition');
            $table->timestampTz('published_at');
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['assessment_template_id', 'version']);
        });
        Schema::create('assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('assessment_template_version_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('IN_PROGRESS');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['patient_encounter_id', 'status']);
        });
        Schema::create('assessment_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained()->restrictOnDelete();
            $table->string('field_key', 120);
            $table->jsonb('value');
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['assessment_id', 'field_key']);
        });
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('recorded_at');
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->ulid('operation_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['organization_id', 'operation_id']);
            $table->index(['patient_encounter_id', 'recorded_at']);
        });
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->ulid('operation_id');
            $table->string('operation_type');
            $table->string('target_type');
            $table->string('target_id', 26);
            $table->unsignedInteger('entity_version')->nullable();
            $table->string('payload_hash', 64);
            $table->string('outcome');
            $table->string('resource_type')->nullable();
            $table->string('resource_id', 26)->nullable();
            $table->string('device_id', 120)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->foreignUlid('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('captured_at');
            $table->timestampTz('processed_at');
            $table->timestampsTz();
            $table->unique(['organization_id', 'operation_id']);
            $table->index(['organization_id', 'processed_at']);
        });

        DB::statement("ALTER TABLE patients ADD CONSTRAINT patients_identity_status_check CHECK (identity_status IN ('UNIDENTIFIED','PARTIALLY_IDENTIFIED','IDENTIFIED'))");
        DB::statement('ALTER TABLE patients ADD CONSTRAINT patients_estimated_age_check CHECK (estimated_age_min IS NULL OR estimated_age_max IS NULL OR estimated_age_min <= estimated_age_max)');
        DB::statement("ALTER TABLE patient_encounters ADD CONSTRAINT patient_encounters_status_check CHECK (status IN ('ACTIVE','STABILIZED','CANCELLED'))");
        DB::statement("ALTER TABLE patient_encounters ADD CONSTRAINT patient_encounters_condition_check CHECK (condition_level IN ('CRITICAL','SERIOUS','MODERATE','STABLE','UNKNOWN'))");
        DB::statement("ALTER TABLE patient_encounters ADD CONSTRAINT patient_encounters_knowledge_check CHECK (allergy_status IN ('UNKNOWN','NONE_KNOWN','KNOWN') AND medication_status IN ('UNKNOWN','NONE_KNOWN','KNOWN') AND history_status IN ('UNKNOWN','NONE_KNOWN','KNOWN'))");
        DB::statement("ALTER TABLE vital_observations ADD CONSTRAINT vital_observations_type_check CHECK (type IN ('HEART_RATE','BLOOD_PRESSURE','SPO2','RESPIRATORY_RATE','TEMPERATURE','GLUCOSE','GCS','PAIN_SCORE'))");
        DB::statement("ALTER TABLE vital_observations ADD CONSTRAINT vital_observations_source_check CHECK (source IN ('MANUAL','DEVICE','IMPORTED','OFFLINE_SYNC'))");
        DB::statement("ALTER TABLE vital_observations ADD CONSTRAINT vital_observations_shape_check CHECK ((type = 'BLOOD_PRESSURE' AND value_numeric IS NOT NULL AND secondary_value_numeric IS NOT NULL AND unit = 'mmHg') OR (type = 'GCS' AND gcs_total IS NOT NULL) OR (type NOT IN ('BLOOD_PRESSURE','GCS') AND value_numeric IS NOT NULL))");
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_status_check CHECK (status IN ('IN_PROGRESS','COMPLETED','AMENDED','CANCELLED'))");
        DB::statement("ALTER TABLE sync_operations ADD CONSTRAINT sync_operations_outcome_check CHECK (outcome IN ('ACCEPTED','CONFLICT','REJECTED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('assessment_responses');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_template_versions');
        Schema::dropIfExists('assessment_templates');
        Schema::dropIfExists('vital_observations');
        Schema::dropIfExists('patient_encounters');
        Schema::dropIfExists('patients');
        DB::statement('ALTER TABLE emergency_cases DROP CONSTRAINT emergency_cases_status_check');
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_status_check CHECK (status IN ('RECEIVED','TRIAGED','UNIT_ASSIGNED','UNIT_ACCEPTED','EN_ROUTE_TO_SCENE','ON_SCENE','CANCELLED','CLOSED'))");
    }
};
