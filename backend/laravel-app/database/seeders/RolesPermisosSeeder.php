<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermisosSeeder extends Seeder
{
    /** Lista completa de permisos del sistema. */
    private array $permisos = [
        // Usuarios
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',

        // Funcionarios
        'funcionarios.ver',
        'funcionarios.crear',
        'funcionarios.editar',
        'funcionarios.eliminar',

        // Cargos
        'cargos.ver',
        'cargos.crear',
        'cargos.editar',

        // Rangos salariales
        'rangos_salariales.ver',
        'rangos_salariales.crear',
        'rangos_salariales.editar',

        // Solicitudes
        'solicitudes.ver',
        'solicitudes.crear',
        'solicitudes.editar',
        'solicitudes.cambiar_estado',
        'solicitudes.aprobar',
        'solicitudes.rechazar',

        // Pagos
        'pagos.ver',
        'pagos.cargar',
        'pagos.validar',
        'pagos.rechazar',

        // Certificados
        'certificados.ver',
        'certificados.generar',
        'certificados.descargar',
        'certificados.anular',

        // Validación pública
        'tokens.validar',

        // Auditoría
        'auditoria.ver',

        // Reportes
        'reportes.ver',

        // Actuaciones administrativas
        'actuaciones.ver',
        'actuaciones.crear',
        'actuaciones.editar',
    ];

    public function run(): void
    {
        // Limpiar caché de Spatie para evitar datos obsoletos tras migrate:fresh
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear todos los permisos
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // ─── Admin: todos los permisos ────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => RoleEnum::Admin->value, 'guard_name' => 'web']);
        $admin->syncPermissions($this->permisos);

        // ─── Secretario: operaciones de revisión y generación ─────────────────
        $secretario = Role::firstOrCreate(['name' => RoleEnum::Secretario->value, 'guard_name' => 'web']);
        $secretario->syncPermissions([
            'funcionarios.ver',
            'cargos.ver',
            'rangos_salariales.ver',
            'solicitudes.ver',
            'solicitudes.editar',
            'solicitudes.cambiar_estado',
            'solicitudes.aprobar',
            'solicitudes.rechazar',
            'pagos.ver',
            'pagos.validar',
            'pagos.rechazar',
            'certificados.ver',
            'certificados.generar',
            'certificados.descargar',
            'certificados.anular',
            'actuaciones.ver',
            'reportes.ver',
        ]);

        // ─── Funcionario: solo crear y ver sus propias solicitudes ────────────
        $funcionario = Role::firstOrCreate(['name' => RoleEnum::Funcionario->value, 'guard_name' => 'web']);
        $funcionario->syncPermissions([
            'solicitudes.ver',
            'solicitudes.crear',
            'pagos.ver',
            'pagos.cargar',
            'certificados.ver',
            'certificados.descargar',
        ]);

        $this->command->info('Roles y permisos creados correctamente.');
    }
}
