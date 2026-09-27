<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestampTz('occurred_at')->index();
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 26)->nullable()->index();
            $table->foreignUlid('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action')->index();
            $table->string('resource_type')->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->string('result')->default('SUCCESS');
            $table->text('reason')->nullable();
            $table->ulid('correlation_id')->index();
            $table->string('session_identifier')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
