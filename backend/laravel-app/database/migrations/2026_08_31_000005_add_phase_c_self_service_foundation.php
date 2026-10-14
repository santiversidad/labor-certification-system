<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('estado')->index();
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->char('download_token_hash', 64)->nullable()->unique();
            $table->timestamp('download_token_expires_at')->nullable();
        });

        Schema::create('ordenes_pago_certificado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_certificacion_id')->unique()->constrained('solicitudes_certificacion')->restrictOnDelete();
            $table->string('referencia', 60)->unique();
            $table->string('estado', 30)->default('pendiente');
            $table->decimal('monto', 14, 2)->nullable();
            $table->char('moneda', 3)->default('COP');
            $table->string('proveedor', 80)->nullable();
            $table->string('referencia_proveedor', 150)->nullable()->unique();
            $table->timestamp('confirmado_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });

        DB::statement("ALTER TABLE ordenes_pago_certificado ADD CONSTRAINT orden_pago_estado_check CHECK (estado IN ('pendiente', 'procesando', 'confirmado', 'fallido', 'cancelado', 'expirado'))");
        DB::statement('CREATE INDEX funcionarios_busqueda_nombre_idx ON funcionarios (apellidos, nombres)');
        DB::statement('CREATE INDEX funcionarios_cargo_estado_idx ON funcionarios (cargo_id, estado)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS funcionarios_cargo_estado_idx');
        DB::statement('DROP INDEX IF EXISTS funcionarios_busqueda_nombre_idx');
        Schema::dropIfExists('ordenes_pago_certificado');

        Schema::table('certificados', function (Blueprint $table) {
            $table->dropUnique(['download_token_hash']);
            $table->dropColumn(['download_token_hash', 'download_token_expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['must_change_password']);
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
