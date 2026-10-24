<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS (spatial, ADR-04), pgvector (RAG, SRS-KBA-06), pg_trgm (fuzzy name
 * search). PostGIS isn't a trusted extension, so on a DB where the app role
 * isn't superuser the extensions must be created once by a superuser first —
 * `infra/bin/sisdata-db-setup.sh` does that; IF NOT EXISTS then no-ops here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['postgis', 'vector', 'pg_trgm'] as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }
    }

    public function down(): void
    {
        // Extensions are shared infrastructure; never dropped by a rollback.
    }
};
