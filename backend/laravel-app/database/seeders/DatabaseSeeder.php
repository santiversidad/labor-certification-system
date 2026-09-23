<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,      // 1. Roles y permisos primero
            UsuariosInicialesSeeder::class,  // 2. Usuarios con roles asignados
            DatosEjemploSeeder::class,       // 3. Cargo y funcionario ficticios de desarrollo
            ParametrosSistemaSeeder::class,  // 4. Parámetros globales del sistema
        ]);
    }
}
