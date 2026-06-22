<?php

namespace Database\Seeders;

use App\Models\ParametroSistema;
use Illuminate\Database\Seeder;

class ParametrosSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            [
                'clave'       => 'requiere_pago_certificado',
                'valor'       => 'false',
                'tipo'        => 'boolean',
                'descripcion' => 'Activa el flujo de pago obligatorio para certificados. '
                               . 'Mientras la Alcaldía no defina la estructura tarifaria, debe permanecer en false.',
            ],
        ];

        foreach ($parametros as $parametro) {
            ParametroSistema::firstOrCreate(
                ['clave' => $parametro['clave']],
                $parametro
            );
        }

        $this->command->info('Parámetros del sistema creados correctamente.');
    }
}
