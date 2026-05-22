<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'name'      => 'Administrador SCL',
                'documento' => '000000001',
                'password'  => Hash::make('password'),
                'estado'    => true,
                'rol'       => RoleEnum::Admin->value,
            ],
            [
                'name'      => 'Secretario SCL',
                'documento' => '000000002',
                'password'  => Hash::make('password'),
                'estado'    => true,
                'rol'       => RoleEnum::Secretario->value,
            ],
            [
                'name'      => 'Funcionario Prueba',
                'documento' => '000000003',
                'password'  => Hash::make('password'),
                'estado'    => true,
                'rol'       => RoleEnum::Funcionario->value,
            ],
        ];

        foreach ($usuarios as $datos) {
            $rol = $datos['rol'];
            unset($datos['rol']);

            $user = User::updateOrCreate(
                ['documento' => $datos['documento']],
                $datos,
            );

            $user->syncRoles([$rol]);

            $this->command->info("Usuario creado: cédula {$user->documento} — {$user->name} [{$rol}]");
        }
    }
}
