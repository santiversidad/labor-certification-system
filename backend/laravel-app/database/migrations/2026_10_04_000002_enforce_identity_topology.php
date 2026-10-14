<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validate_user_identity(target bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE account users%ROWTYPE; role_count integer; account_role text; employee_count integer;
BEGIN
 SELECT * INTO account FROM users WHERE id=target;
 IF NOT FOUND OR account.tipo_cuenta='legacy' THEN RETURN; END IF;
 SELECT count(*),min(r.name) INTO role_count,account_role FROM model_has_roles m JOIN roles r ON r.id=m.role_id
  WHERE m.model_id=target AND m.model_type='App\Models\User';
 SELECT count(*) INTO employee_count FROM funcionarios f WHERE f.user_id=target;
 IF account.tipo_cuenta='funcionario' THEN
  IF employee_count<>1 OR role_count<>1 OR account_role<>'funcionario'
   OR NOT EXISTS(SELECT 1 FROM funcionarios f WHERE f.user_id=target AND f.numero_documento=account.documento) THEN
   RAISE EXCEPTION 'ACCOUNT_CONTEXT_MISMATCH' USING ERRCODE='23514';
  END IF;
 ELSIF account.tipo_cuenta='backoffice' THEN
  IF employee_count<>0 OR role_count<>1 OR account_role NOT IN ('admin','secretario') THEN
   RAISE EXCEPTION 'INVALID_ACCOUNT_ROLE' USING ERRCODE='23514';
  END IF;
 ELSE
  RAISE EXCEPTION 'ACCOUNT_CONTEXT_MISMATCH' USING ERRCODE='23514';
 END IF;
END $$;
CREATE OR REPLACE FUNCTION enforce_user_identity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_TABLE_NAME='users' THEN
  IF TG_OP<>'DELETE' THEN PERFORM validate_user_identity(NEW.id); END IF;
 ELSIF TG_TABLE_NAME='funcionarios' THEN
  IF TG_OP<>'INSERT' AND OLD.user_id IS NOT NULL THEN PERFORM validate_user_identity(OLD.user_id); END IF;
  IF TG_OP<>'DELETE' AND NEW.user_id IS NOT NULL THEN PERFORM validate_user_identity(NEW.user_id); END IF;
 ELSE
  IF TG_OP<>'INSERT' AND OLD.model_type='App\Models\User' THEN PERFORM validate_user_identity(OLD.model_id); END IF;
  IF TG_OP<>'DELETE' AND NEW.model_type='App\Models\User' THEN PERFORM validate_user_identity(NEW.model_id); END IF;
 END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER users_identity_topology AFTER INSERT OR UPDATE ON users DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION enforce_user_identity();
CREATE CONSTRAINT TRIGGER funcionarios_identity_topology AFTER INSERT OR UPDATE OR DELETE ON funcionarios DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION enforce_user_identity();
CREATE CONSTRAINT TRIGGER roles_identity_topology AFTER INSERT OR UPDATE OR DELETE ON model_has_roles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION enforce_user_identity();
CREATE OR REPLACE FUNCTION preserve_user_actor() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'USER_DELETION_PROHIBITED' USING ERRCODE='23514'; END $$;
CREATE TRIGGER preserve_user_actor BEFORE DELETE ON users FOR EACH ROW EXECUTE FUNCTION preserve_user_actor();
SQL);
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('documento')->exists()) {
            throw new RuntimeException('IDENTITY_ROLLBACK_REQUIRES_OPERATOR: restore the backup before identity provisioning.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS preserve_user_actor ON users;
                DROP TRIGGER IF EXISTS roles_identity_topology ON model_has_roles;
                DROP TRIGGER IF EXISTS funcionarios_identity_topology ON funcionarios;
                DROP TRIGGER IF EXISTS users_identity_topology ON users;
                DROP FUNCTION IF EXISTS preserve_user_actor();
                DROP FUNCTION IF EXISTS enforce_user_identity();
                DROP FUNCTION IF EXISTS validate_user_identity(bigint);');
        }
    }
};
