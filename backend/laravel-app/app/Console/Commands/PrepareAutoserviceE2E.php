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
            $admin = User::firstOrCreate(['documento' => 'E2E-ADMIN'], [
                'name' => 'Administrador ficticio E2E', 'password' => Hash::make('E2EAdminClave2026'),
                'estado' => true, 'must_change_password' => false,
            ]);
            $admin->assignRole('admin');
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
                'name' => 'Funcionario ficha incompleta E2E', 'password' => Hash::make('E2EIncomplete2026'),
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
        });
        $this->info('Fixtures sintéticos preparados en la base E2E aislada; no se eliminaron registros.');

        return self::SUCCESS;
    }
}
