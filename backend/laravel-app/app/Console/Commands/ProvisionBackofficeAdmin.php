<?php

namespace App\Console\Commands;

use App\Exceptions\IdentityException;
use App\Models\User;
use App\Services\BackofficeIdentityService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ProvisionBackofficeAdmin extends Command
{
    protected $signature = 'backoffice:provision-admin {--name=} {--username=} {--email=} {--legacy-user=} {--role=admin}';

    protected $description = 'Provision an individual administrator or explicitly migrate a legacy actor; emits one temporary password';

    public function handle(BackofficeIdentityService $service): int
    {
        try {
            $username = $this->option('username') ?: $this->ask('Usuario individual institucional');
            if ($legacy = $this->option('legacy-user')) {
                $result = $service->provisionLegacy(User::findOrFail($legacy), $username, $this->option('role'));
            } else {
                $result = $service->create(['name' => $this->option('name') ?: $this->ask('Nombre descriptivo'),
                    'username' => $username, 'email' => $this->option('email'), 'role' => 'admin']);
            }
            $this->info('Cuenta '.$result['user']->id.' provisionada. Cambio de contraseña obligatorio.');
            $this->warn('Comparta por un canal institucional seguro. No se volverá a mostrar.');
            $this->line($result['temporary_password']);

            return self::SUCCESS;
        } catch (IdentityException $e) {
            $this->error($e->domainCode.': '.$e->getMessage());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $errors) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }
        }

        return self::FAILURE;
    }
}
