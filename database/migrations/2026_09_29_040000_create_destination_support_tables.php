<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_encounters', function (Blueprint $table) {
            $table->unsignedInteger('destination_version')->default(1);
        });

        Schema::create('destination_hospital_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignUlid('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['organization_id', 'hospital_id']);
            $table->index(['organization_id', 'active']);
        });

        Schema::create('destination_requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('target_code', 100);
            $table->string('importance');
            $table->string('source')->default('MANUAL');
            $table->string('status')->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->foreignUlid('entered_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->ulid('supersedes_requirement_id')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'patient_encounter_id', 'status'], 'destination_requirements_current_idx');
        });

        Schema::create('destination_requirement_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('captured_at');
            $table->timestampsTz();
            $table->index(['patient_encounter_id', 'captured_at']);
        });

        Schema::create('destination_requirement_set_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('destination_requirement_set_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_requirement_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['destination_requirement_set_id', 'destination_requirement_id'], 'destination_requirement_set_item_unique');
        });

        Schema::create('destination_rule_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_synthetic')->default(false);
            $table->timestampsTz();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('destination_rule_set_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_rule_set_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('DRAFT');
            $table->jsonb('definition');
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();
            $table->unique(['destination_rule_set_id', 'version']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('destination_evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_requirement_set_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_rule_set_version_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('PENDING');
            $table->timestampTz('reference_time');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignUlid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampsTz();
            $table->index(['patient_encounter_id', 'created_at']);
        });

        Schema::create('destination_candidate_evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('destination_evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->string('outcome');
            $table->text('explanation_summary');
            $table->jsonb('snapshot_data');
            $table->jsonb('evidence_data');
            $table->timestampsTz();
            $table->unique(['destination_evaluation_id', 'hospital_id'], 'destination_candidate_unique');
            $table->index(['destination_evaluation_id', 'outcome']);
        });

        Schema::create('destination_selections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('emergency_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_evaluation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('destination_candidate_evaluation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('hospital_id')->constrained()->restrictOnDelete();
            $table->string('selection_type');
            $table->text('reason')->nullable();
            $table->foreignUlid('selected_by')->constrained('users')->restrictOnDelete();
            $table->ulid('supersedes_selection_id')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->foreignUlid('superseded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->timestampsTz();
            $table->index(['patient_encounter_id', 'created_at']);
        });

        Schema::table('destination_requirements', function (Blueprint $table) {
            $table->foreign('supersedes_requirement_id')->references('id')->on('destination_requirements')->restrictOnDelete();
        });
        Schema::table('destination_selections', function (Blueprint $table) {
            $table->foreign('supersedes_selection_id')->references('id')->on('destination_selections')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE emergency_cases DROP CONSTRAINT emergency_cases_status_check');
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_status_check CHECK (status IN ('RECEIVED','TRIAGED','UNIT_ASSIGNED','UNIT_ACCEPTED','EN_ROUTE_TO_SCENE','ON_SCENE','PATIENT_CONTACT','ASSESSMENT','DESTINATION_PENDING','DESTINATION_SELECTED','CANCELLED','CLOSED'))");
        DB::statement("ALTER TABLE destination_requirements ADD CONSTRAINT destination_requirements_type_check CHECK (type IN ('CAPABILITY_REQUIRED','CAPABILITY_PREFERRED','RESOURCE_REQUIRED','RECEIVING_REQUIRED'))");
        DB::statement("ALTER TABLE destination_requirements ADD CONSTRAINT destination_requirements_importance_check CHECK (importance IN ('REQUIRED','PREFERRED'))");
        DB::statement("ALTER TABLE destination_requirements ADD CONSTRAINT destination_requirements_source_check CHECK (source IN ('MANUAL','PROTOCOL','INTEGRATION'))");
        DB::statement("ALTER TABLE destination_requirements ADD CONSTRAINT destination_requirements_status_check CHECK (status IN ('ACTIVE','SUPERSEDED','CANCELLED'))");
        DB::statement("ALTER TABLE destination_rule_set_versions ADD CONSTRAINT destination_rule_version_status_check CHECK (status IN ('DRAFT','ACTIVE','RETIRED'))");
        DB::statement("CREATE UNIQUE INDEX destination_rule_version_active_unique ON destination_rule_set_versions (organization_id, destination_rule_set_id) WHERE status = 'ACTIVE'");
        DB::statement("ALTER TABLE destination_evaluations ADD CONSTRAINT destination_evaluations_status_check CHECK (status IN ('PENDING','RUNNING','COMPLETED','FAILED','CANCELLED'))");
        DB::statement('CREATE UNIQUE INDEX destination_evaluations_idempotency_unique ON destination_evaluations (organization_id,idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement("ALTER TABLE destination_candidate_evaluations ADD CONSTRAINT destination_candidate_outcome_check CHECK (outcome IN ('ELIGIBLE','INELIGIBLE','UNKNOWN'))");
        DB::statement("ALTER TABLE destination_selections ADD CONSTRAINT destination_selection_type_check CHECK (selection_type IN ('ELIGIBLE_SELECTION','UNKNOWN_OVERRIDE','INELIGIBLE_OVERRIDE','MANUAL_WITHOUT_EVALUATION'))");
        DB::statement('CREATE UNIQUE INDEX destination_selection_active_unique ON destination_selections (patient_encounter_id) WHERE superseded_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX destination_selection_idempotency_unique ON destination_selections (organization_id,idempotency_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_selections');
        Schema::dropIfExists('destination_candidate_evaluations');
        Schema::dropIfExists('destination_evaluations');
        Schema::dropIfExists('destination_rule_set_versions');
        Schema::dropIfExists('destination_rule_sets');
        Schema::dropIfExists('destination_requirement_set_items');
        Schema::dropIfExists('destination_requirement_sets');
        Schema::dropIfExists('destination_requirements');
        Schema::dropIfExists('destination_hospital_access');
        Schema::table('patient_encounters', fn (Blueprint $table) => $table->dropColumn('destination_version'));
        Schema::table('destination_requirements', function (Blueprint $table) {
            $table->foreign('supersedes_requirement_id')->references('id')->on('destination_requirements')->restrictOnDelete();
        });
        Schema::table('destination_selections', function (Blueprint $table) {
            $table->foreign('supersedes_selection_id')->references('id')->on('destination_selections')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE emergency_cases DROP CONSTRAINT emergency_cases_status_check');
        DB::statement("ALTER TABLE emergency_cases ADD CONSTRAINT emergency_cases_status_check CHECK (status IN ('RECEIVED','TRIAGED','UNIT_ASSIGNED','UNIT_ACCEPTED','EN_ROUTE_TO_SCENE','ON_SCENE','PATIENT_CONTACT','ASSESSMENT','DESTINATION_PENDING','CANCELLED','CLOSED'))");
    }
};
