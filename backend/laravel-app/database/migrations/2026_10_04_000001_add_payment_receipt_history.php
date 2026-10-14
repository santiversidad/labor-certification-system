<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_soporte_id')->constrained('pagos_soportes')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('archivo_path', 500);
            $table->string('nombre_original');
            $table->string('mime_type', 80);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->string('referencia', 150)->nullable();
            $table->text('observacion_funcionario')->nullable();
            $table->string('estado', 20)->default('en_revision');
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();
            $table->unique(['pago_soporte_id', 'version']);
        });
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->string('error_expedicion', 80)->nullable();
        });
        DB::statement("ALTER TABLE pago_comprobantes ADD CONSTRAINT pago_comprobante_estado_check CHECK (estado IN ('en_revision', 'rechazado', 'confirmado'))");
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', fn (Blueprint $table) => $table->dropColumn('error_expedicion'));
        Schema::dropIfExists('pago_comprobantes');
    }
};
