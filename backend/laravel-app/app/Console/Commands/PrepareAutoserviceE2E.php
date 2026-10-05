<?php

namespace App\Console\Commands;

use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualFuncion;
use App\Models\ParametroSistema;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrepareAutoserviceE2E extends Command
{
    protected $signature = 'e2e:prepare-autoservice';

    protected $description = 'Prepare synthetic fixtures, exclusively in isolated E2E databases';

    public function handle(): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'scl_certificate_types_e2e') {
            $this->error('E2E_ISOLATION_REQUIRED');

            return self::FAILURE;
        }
        $this->call('db:seed', ['--class' => RolesPermisosSeeder::class, '--force' => true]);
        DB::transaction(function () {
            $admin = User::firstOrCreate(['username' => 'e2e-admin'], [
                'tipo_cuenta' => 'backoffice', 'documento' => null, 'name' => 'Administrador ficticio E2E', 'password' => Hash::make('E2EAdminClave2026'),
                'estado' => true, 'must_change_password' => false,
            ]);
            $admin->assignRole('admin');
            $this->mfaFixture($admin);
            foreach (['E2E-SECRETARIA', 'E2E-SECRETARIA-2'] as $documento) {
                $secretaria = User::firstOrCreate(['username' => strtolower($documento)], [
                    'tipo_cuenta' => 'backoffice', 'documento' => null, 'name' => 'Secretaría ficticia pagos E2E', 'password' => Hash::make('E2ESecretaria2026'),
                    'estado' => true, 'must_change_password' => false,
                ]);
                $secretaria->assignRole('secretario');
                $this->mfaFixture($secretaria);
            }
            $cargo = Cargo::firstOrCreate(['codigo' => 'E2E', 'grado' => '01'], [
                'denominacion' => 'Profesional E2E', 'nivel' => 'Profesional',
                'dependencia' => 'Talento Humano E2E', 'estado' => true,
            ]);
            $manual = ManualFuncion::firstOrCreate(['codigo' => 'E2E-SINTETICO'], ['nombre' => 'Manual ficticio E2E']);
            $version = $manual->versiones()->firstOrCreate(['version' => 'E2E-1'], [
                'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'E2E',
                'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
            ]);
            if ($version->estado === 'borrador') {
                $ficha = $version->cargos()->create([
                    'cargo_id' => $cargo->id, 'source_id' => 'E2E-001',
                    'area_funcional' => 'Autoservicio E2E', 'dependencia' => 'Talento Humano E2E',
                    'proposito_principal' => 'Verificar un flujo aislado.',
                ]);
                $ficha->funciones()->create(['orden' => 1, 'descripcion' => 'Atender pruebas sintéticas.']);
                for ($order = 2; $order <= 24; $order++) {
                    $ficha->funciones()->create([
                        'orden' => $order,
                        'descripcion' => "Función extensa ficticia E2E {$order}: gestionar información institucional de prueba con precisión, trazabilidad, oportunidad y criterios verificables de calidad, sin alterar el orden definido en el snapshot.",
                    ]);
                }
                $version->update(['estado' => 'publicado']);
            }

            $cargoIncompleto = Cargo::firstOrCreate(['codigo' => 'E2I', 'grado' => '01'], [
                'denominacion' => 'Profesional Incompleto E2E', 'nivel' => 'Profesional',
                'dependencia' => 'Talento Humano E2E', 'estado' => true,
            ]);
            $manualIncompleto = ManualFuncion::firstOrCreate(['codigo' => 'E2E-INCOMPLETO'], ['nombre' => 'Manual incompleto ficticio E2E']);
            $versionIncompleta = $manualIncompleto->versiones()->firstOrCreate(['version' => 'E2E-INC-1'], [
                'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'E2E-INC',
                'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
            ]);
            $fichaIncompleta = $versionIncompleta->cargos()->firstOrCreate(['cargo_id' => $cargoIncompleto->id], [
                'source_id' => 'E2E-INC-001', 'area_funcional' => 'Validación incompleta E2E',
                'dependencia' => 'Talento Humano E2E', 'proposito_principal' => 'Validar el rechazo normativo controlado.',
            ]);
            if ($versionIncompleta->estado === 'borrador') {
                $versionIncompleta->update(['estado' => 'publicado']);
            }
            $incompleteUser = User::firstOrCreate(['documento' => 'E2E-INCOMPLETE'], [
                'tipo_cuenta' => 'funcionario', 'name' => 'Funcionario ficha incompleta E2E', 'password' => Hash::make('E2EIncomplete2026'),
                'estado' => true, 'must_change_password' => false,
            ]);
            $incompleteUser->assignRole('funcionario');
            $incompleteEmployee = Funcionario::firstOrCreate(['numero_documento' => 'E2E-INCOMPLETE'], [
                'user_id' => $incompleteUser->id, 'tipo_documento' => 'CC', 'nombres' => 'Funcionario',
                'apellidos' => 'Ficha Incompleta E2E', 'estado' => 'activo', 'fecha_ingreso' => '2024-01-15',
                'dependencia' => 'Talento Humano E2E', 'cargo_id' => $cargoIncompleto->id,
            ]);
            $assignment = FuncionarioCargo::firstOrCreate([
                'funcionario_id' => $incompleteEmployee->id, 'fecha_inicio' => '2024-01-15',
            ], [
                'cargo_id' => $cargoIncompleto->id, 'manual_cargo_version_id' => $fichaIncompleta->id,
                'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa',
                'es_cargo_base' => true, 'es_encargo' => false,
            ]);
            $assignment->fichasNormativas()->firstOrCreate([
                'manual_cargo_version_id' => $fichaIncompleta->id, 'vigencia_desde' => '2024-01-15',
            ], ['origen' => 'fixture_e2e']);
            ParametroSistema::updateOrCreate(['clave' => 'requiere_pago_certificado'], ['valor' => 'false', 'tipo' => 'boolean']);
            $scopedManual = ManualFuncion::firstOrCreate(['codigo' => 'E2E-UNIDADES'], ['nombre' => 'Manual ficticio de selección por dependencia']);
            $scopedVersion = $scopedManual->versiones()->firstOrCreate(['version' => 'E2E-UNIDADES-1'], [
                'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'E2E-UNIDADES',
                'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
            ]);
            if ($scopedVersion->estado === 'borrador') {
                $sharedCargo = Cargo::firstOrCreate(['codigo' => 'DPE', 'grado' => '02'], [
                    'denominacion' => 'Profesional de dependencias E2E', 'nivel' => 'Profesional', 'estado' => true,
                ]);
                foreach (['A', 'B'] as $unidad) {
                    $only = Cargo::firstOrCreate(['codigo' => 'DP'.$unidad, 'grado' => '03'], [
                        'denominacion' => 'Cargo exclusivo '.$unidad.' E2E', 'nivel' => 'Profesional', 'estado' => true,
                    ]);
                    foreach ([$sharedCargo, $only] as $position) {
                        $profile = $scopedVersion->cargos()->create(['cargo_id' => $position->id,
                            'source_id' => 'E2E-UNIDAD-'.$unidad.'-'.$position->codigo,
                            'dependencia' => 'Donde se ubique el cargo', 'area_funcional' => 'Dependencia '.$unidad.' E2E',
                            'proposito_principal' => 'Validar selección aislada en unidad '.$unidad,
                        ]);
                        $profile->funciones()->create(['orden' => 1, 'descripcion' => 'Función exclusiva de dependencia '.$unidad.' E2E.']);
                    }
                }
                $scopedVersion->update(['estado' => 'publicado']);
            }
            $ambiguousCargo = Cargo::firstOrCreate(['codigo' => 'DPM', 'grado' => '02'], [
                'denominacion' => 'Cargo que requiere revisión E2E', 'nivel' => 'Profesional', 'estado' => true,
            ]);
            $ambiguousManual = ManualFuncion::firstOrCreate(['codigo' => 'E2E-AMBIGUEDAD-UX'], ['nombre' => 'Manual sintético de ambigüedad UX']);
            $ambiguousVersion = $ambiguousManual->versiones()->firstOrCreate(['version' => 'E2E-AMBIGUEDAD-UX-1'], [
                'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'E2E-AMBIGUEDAD-UX',
                'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
            ]);
            if ($ambiguousVersion->estado === 'borrador') {
                foreach ([1, 2] as $index) {
                    $profile = $ambiguousVersion->cargos()->create(['cargo_id' => $ambiguousCargo->id,
                        'source_id' => 'E2E-AMBIGUEDAD-UX-'.$index, 'dependencia' => 'Donde se ubique el cargo',
                        'area_funcional' => 'Dependencia A E2E', 'proposito_principal' => 'Verificar bloqueo de selección ambigua.',
                    ]);
                    $profile->funciones()->create(['orden' => 1, 'descripcion' => 'Función sintética de ambigüedad '.$index]);
                }
                $ambiguousVersion->update(['estado' => 'publicado']);
            }
            $globalCargo = Cargo::firstOrCreate(['codigo' => 'DPG', 'grado' => '01'], [
                'denominacion' => 'Cargo transversal E2E', 'nivel' => 'Profesional', 'estado' => true,
            ]);
            $globalManual = ManualFuncion::firstOrCreate(['codigo' => 'E2E-TRANSVERSAL'], ['nombre' => 'Manual ficticio de aplicabilidad transversal']);
            $globalVersion = $globalManual->versiones()->firstOrCreate(['version' => 'E2E-TRANSVERSAL-1'], [
                'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'E2E-TRANSVERSAL',
                'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
            ]);
            if ($globalVersion->estado === 'borrador') {
                $globalProfile = $globalVersion->cargos()->create(['cargo_id' => $globalCargo->id,
                    'source_id' => 'E2E-TRANSVERSAL-001', 'dependencia' => 'Donde se ubique el cargo',
                    'area_funcional' => 'Todas las dependencias', 'proposito_principal' => 'Validar la aplicabilidad explícita transversal.',
                ]);
                $globalProfile->funciones()->create(['orden' => 1, 'descripcion' => 'Función transversal E2E.']);
                $globalVersion->update(['estado' => 'publicado']);
            }
        });
        $this->info('Fixtures sintéticos preparados en la base E2E aislada; no se eliminaron registros.');

        return self::SUCCESS;
    }

    private function mfaFixture(User $user): void
    {
        // Synthetic, exclusively behind the existing testing + exact E2E database guard.
        // Larger fixture pool lets preexisting regressions authenticate without TOTP sleeps.
        $codes = array_map(fn ($index) => hash_hmac('sha256', $user->username.':'.$index, 'e2e-only-fixture-seed'), range(0, 511));
        $user->forceFill(['password' => str_contains($user->username, 'secretaria') ? 'E2ESecretaria2026' : 'E2EAdminClave2026',
            'estado' => true, 'must_change_password' => false, 'mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'mfa_confirmed_at' => now(), 'mfa_last_step' => null,
            'mfa_recovery_hashes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();
        $user->revokeAuthentication();
    }
}
