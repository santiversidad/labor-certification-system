<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,    // 1. Roles y permisos primero
            UsuariosInicialesSeeder::class, // 2. Usuarios con roles asignados
            DatosEjemploSeeder::class,      // 3. Cargos y rangos salariales de ejemplo
        ]);
    }
}
