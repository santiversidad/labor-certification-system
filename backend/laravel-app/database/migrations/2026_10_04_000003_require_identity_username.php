<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // SQL CHECK accepts UNKNOWN; require NOT NULL explicitly for every ready identity.
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_ready_username_required
                CHECK (tipo_cuenta='legacy' OR username IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_ready_username_required');
        }
    }
};
