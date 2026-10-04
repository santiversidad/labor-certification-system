<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                RolesPermisosSeeder::class,      // 1. Roles y permisos primero
                UsuariosInicialesSeeder::class,  // 2. Usuarios con roles asignados
                DatosEjemploSeeder::class,       // 3. Cargo y funcionario sintéticos de desarrollo
                ParametrosSistemaSeeder::class,  // 4. Parámetros globales del sistema
            ]);
        });
    }
}
