<?php

namespace App\Console\Commands;

use App\Models\Cargo;
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

    protected $description = 'Prepare synthetic fixtures, exclusively in scl_e2e_test';

    public function handle(): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'scl_e2e_test') {
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
            ParametroSistema::updateOrCreate(['clave' => 'requiere_pago_certificado'], ['valor' => 'false', 'tipo' => 'boolean']);
        });
        $this->info('Fixtures sintéticos preparados en scl_e2e_test; no se eliminaron registros.');

        return self::SUCCESS;
    }
}
