<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsuariosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command->info('Use backoffice:provision-admin para crear identidades individuales.');

            return;
        }
        foreach (['admin_test' => 'admin', 'secretario_test' => 'secretario'] as $username => $role) {
            $user = User::firstOrCreate(['username' => $username], [
                'name' => 'Identidad sintética '.$username, 'tipo_cuenta' => 'backoffice', 'documento' => null,
                'password' => 'DevFixtureClave2026', 'estado' => true, 'must_change_password' => true,
            ]);
            $user->syncRoles([$role]);
        }
        $user = User::firstOrCreate(['documento' => '000000003'], [
            'name' => 'Funcionario Prueba', 'tipo_cuenta' => 'funcionario',
            'password' => '000000003', 'estado' => true, 'must_change_password' => true,
        ]);
        $user->syncRoles(['funcionario']);
    }
}
