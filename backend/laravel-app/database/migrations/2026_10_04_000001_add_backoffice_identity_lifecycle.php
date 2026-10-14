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
            $table->string('documento', 20)->nullable()->change();
            $table->string('username', 64)->nullable()->unique();
            $table->string('tipo_cuenta', 20)->default('legacy');
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('desactivado_at')->nullable();
            $table->foreignId('desactivado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_desactivacion')->nullable();
        });
        // Only unambiguous employee identities can be classified without an operator.
        DB::statement("UPDATE users u SET tipo_cuenta = 'funcionario', username = lower(trim(u.documento))
            WHERE EXISTS (SELECT 1 FROM funcionarios f WHERE f.user_id=u.id AND f.numero_documento=u.documento)
            AND (SELECT count(*) FROM model_has_roles m WHERE m.model_id=u.id AND m.model_type='App\\Models\\User')=1
            AND EXISTS (SELECT 1 FROM model_has_roles m JOIN roles r ON r.id=m.role_id
                WHERE m.model_id=u.id AND m.model_type='App\\Models\\User' AND r.name='funcionario')");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_account_identity_check CHECK (
                tipo_cuenta='legacy' OR
                (tipo_cuenta='funcionario' AND documento IS NOT NULL AND username=lower(trim(documento))) OR
                (tipo_cuenta='backoffice' AND documento IS NULL AND username IS NOT NULL
                 AND username=lower(username) AND username ~ '^[a-z0-9._-]{3,64}$' AND username !~ '^[0-9]+$'))");
            DB::statement('CREATE UNIQUE INDEX funcionarios_user_identity_unique ON funcionarios(user_id) WHERE user_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        // Backoffice identities cannot be represented by the old mandatory document.
        if (DB::table('users')->whereNull('documento')->exists()) {
            throw new RuntimeException('IDENTITY_ROLLBACK_REQUIRES_OPERATOR: restore the backup before identity provisioning.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_identity_check');
            DB::statement('DROP INDEX IF EXISTS funcionarios_user_identity_unique');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desactivado_por');
            $table->dropColumn(['username', 'tipo_cuenta', 'auth_version', 'last_login_at', 'desactivado_at', 'motivo_desactivacion']);
            $table->string('documento', 20)->nullable(false)->change();
        });
    }
};
