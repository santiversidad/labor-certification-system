<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\RangoSalarial;
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
    }
}
