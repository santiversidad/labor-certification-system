<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $retirados = ['funcionarios.ver', 'cargos.ver', 'solicitudes.editar', 'solicitudes.cambiar_estado', 'solicitudes.aprobar', 'solicitudes.rechazar', 'certificados.generar', 'certificados.anular', 'actuaciones.ver', 'reportes.ver'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'funcionario')->where('guard_name', 'web')->first()?->givePermissionTo(['solicitudes.ver', 'pagos.cargar']);
        Role::where('name', 'secretario')->where('guard_name', 'web')->first()?->revokePermissionTo($this->retirados);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'funcionario')->where('guard_name', 'web')->first()?->revokePermissionTo(['solicitudes.ver', 'pagos.cargar']);
        Role::where('name', 'secretario')->where('guard_name', 'web')->first()?->givePermissionTo($this->retirados);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
