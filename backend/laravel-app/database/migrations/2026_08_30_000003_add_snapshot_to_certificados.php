<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('certificados')
            ->select('solicitud_certificacion_id', DB::raw('COUNT(*) AS total'))
            ->groupBy('solicitud_certificacion_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicados) {
            throw new RuntimeException(
                'Existen varios certificados para una misma solicitud. '
                .'La migracion se cancela sin eliminar registros.'
            );
        }

        Schema::table('certificados', function (Blueprint $table) {
            $table->unsignedSmallInteger('snapshot_schema_version')->nullable()->after('hash_pdf');
            $table->jsonb('snapshot_datos')->nullable()->after('snapshot_schema_version');
            $table->unique('solicitud_certificacion_id', 'certificados_solicitud_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropUnique('certificados_solicitud_unique');
            $table->dropColumn(['snapshot_schema_version', 'snapshot_datos']);
        });
    }
};
