<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('scope')->index();
            $table->boolean('is_system')->default(true);
            $table->timestampsTz();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestampsTz();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUlid('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('membership_role', function (Blueprint $table) {
            $table->foreignUlid('organization_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
            $table->primary(['organization_membership_id', 'role_id']);
        });
        Schema::create('platform_role_user', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('role_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
            $table->primary(['user_id', 'role_id']);
        });
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_scope_check CHECK (scope IN ('PLATFORM', 'ORGANIZATION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_user');
        Schema::dropIfExists('membership_role');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
