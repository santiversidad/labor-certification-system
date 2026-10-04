<?php

namespace App\Console\Commands;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoFuncionarioEnum;
use App\Exceptions\IdentityException;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionFuncionarioAccount extends Command
{
    protected $signature = 'funcionario:provision-account {funcionario}';

    protected $description = 'Explicitly provision a separate self-service identity for an existing employee without an account';

    public function handle(RegistrarAuditoriaAction $audit): int
    {
        try {
            $user = DB::transaction(function () use ($audit) {
                $employee = Funcionario::lockForUpdate()->findOrFail($this->argument('funcionario'));
                if ($employee->user_id !== null || User::where('username', strtolower(trim($employee->numero_documento)))->exists()) {
                    throw new IdentityException('EMPLOYEE_ACCOUNT_EXISTS', 'El funcionario o documento ya tiene una cuenta; revise la identidad existente.');
                }
                $user = User::create(['tipo_cuenta' => 'funcionario', 'name' => trim($employee->nombres.' '.$employee->apellidos),
                    'documento' => $employee->numero_documento, 'password' => $employee->numero_documento,
                    'estado' => $employee->estado === EstadoFuncionarioEnum::Activo,
                    'must_change_password' => true]);
                $user->syncRoles(['funcionario']);
                $employee->update(['user_id' => $user->id]);
                $user->assertIdentity();
                $audit->execute(accion: 'crear_usuario_funcionario', modelo: 'User', modeloId: $user->id,
                    descripcion: 'Cuenta de autoservicio provisionada explícitamente.',
                    metadata: ['funcionario_id' => $employee->id, 'origin' => 'operational_command']);

                return $user;
            });
            $this->info('Cuenta independiente '.$user->id.' creada. Contraseña inicial institucional; cambio obligatorio.');

            return self::SUCCESS;
        } catch (IdentityException $e) {
            $this->error($e->domainCode.': '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
