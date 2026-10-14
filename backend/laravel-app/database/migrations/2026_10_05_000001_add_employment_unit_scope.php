<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionario_cargo', function (Blueprint $table) {
            // Stable key of a derived Manual scope, not a second normative catalog.
            // Legacy assignments remain null: do not reinterpret historical facts.
            $table->string('unidad_organizacional_id', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('EMPLOYMENT_UNIT_ROLLBACK_REQUIRES_REVIEW');
    }
};
