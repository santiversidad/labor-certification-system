<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            // Número de radicado institucional único, ej: CL-2026-000001
            $table->string('radicado', 30)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropColumn('radicado');
        });
    }
};
