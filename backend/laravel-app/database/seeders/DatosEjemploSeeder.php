<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\RangoSalarial;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatosEjemploSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Cargo de ejemplo ─────────────────────────────────────────────────
        $cargo = Cargo::firstOrCreate(
            ['codigo' => '219', 'grado' => '02'],
            [
                'denominacion' => 'Profesional Universitario',
                'nivel'        => 'Profesional',
                'dependencia'  => 'Dirección de Personal',
                'estado'       => true,
            ]
        );

        $this->command->info("Cargo creado: {$cargo->denominacion} ({$cargo->codigo}-{$cargo->grado})");

        // ─── Rango salarial de ejemplo ────────────────────────────────────────
        $rango = RangoSalarial::firstOrCreate(
            ['codigo' => '219', 'grado' => '02', 'vigencia_anio' => 2026],
            [
                'salario_basico' => 3450000.00,
                'moneda'         => 'COP',
                'observaciones'  => 'Salario básico según decreto de salarios 2026.',
                'estado'         => true,
            ]
        );

        $this->command->info("Rango salarial creado: código {$rango->codigo} grado {$rango->grado} vigencia {$rango->vigencia_anio} — \${$rango->salario_basico} {$rango->moneda}");

        // ─── Funcionario de prueba ────────────────────────────────────────────
        // Vinculado al usuario con documento 000000003 (rol: funcionario)
        $userFuncionario = User::where('documento', '000000003')->first();

        if ($userFuncionario) {
            $funcionario = Funcionario::firstOrCreate(
                ['user_id' => $userFuncionario->id],
                [
                    'tipo_documento'        => 'CC',
                    'numero_documento'      => '000000003',
                    'nombres'               => 'Funcionario',
                    'apellidos'             => 'Prueba',
                    'correo_institucional'  => 'funcionario@villavicencio.gov.co',
                    'telefono'              => '3001234567',
                    'estado'                => 'activo',
                    'fecha_ingreso'         => '2020-01-15',
                    'dependencia'           => 'Dirección de Personal',
                    'cargo_id'              => $cargo->id,
                ]
            );

            $this->command->info("Funcionario de prueba creado: {$funcionario->nombres} {$funcionario->apellidos} (user_id: {$userFuncionario->id})");
        }
    }
}
