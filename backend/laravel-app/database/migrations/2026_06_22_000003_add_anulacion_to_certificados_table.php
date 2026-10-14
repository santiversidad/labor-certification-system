<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->text('motivo_anulacion')->nullable()->after('estado');
            $table->foreignId('anulado_por')->nullable()->after('motivo_anulacion')->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['motivo_anulacion', 'anulado_at']);
        });
    }
};
