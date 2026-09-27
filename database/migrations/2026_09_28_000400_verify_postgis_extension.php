<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('MEDGRID requires PostgreSQL with PostGIS.');
        }
        $available = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'postgis') AS enabled");
        if (! filter_var($available->enabled, FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('PostGIS is not enabled. Run CREATE EXTENSION postgis as a PostgreSQL administrator.');
        }
    }

    public function down(): void {}
};
