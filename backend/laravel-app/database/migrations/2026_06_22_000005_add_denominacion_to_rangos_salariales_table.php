<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rangos_salariales', function (Blueprint $table) {
            $table->string('denominacion', 200)->nullable()->after('grado');
        });
    }

    public function down(): void
    {
        Schema::table('rangos_salariales', function (Blueprint $table) {
            $table->dropColumn('denominacion');
        });
    }
};
