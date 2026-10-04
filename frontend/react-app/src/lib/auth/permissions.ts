import type { Role } from '../../types/roles.types';
import type { User } from '../../types/common.types';

export function hasAnyRole(user: User | null, allowedRoles: Role[]): boolean {
  if (!user) {
    return false;
  }

  return user.roles.length === 1 && user.roles.some((role) => allowedRoles.includes(role)
    && (role === 'funcionario' ? user.tipo_cuenta === 'funcionario' : user.tipo_cuenta === 'backoffice'));
}

export function isAdmin(user: User | null): boolean {
  return hasAnyRole(user, ['admin']);
}

// Admin y secretario comparten el panel de gestión /admin
export function isGestorRole(user: User | null): boolean {
  return hasAnyRole(user, ['admin', 'secretario']);
}

export function isSecretary(user: User | null): boolean {
  return hasAnyRole(user, ['secretario']);
}

export function isFuncionario(user: User | null): boolean {
  return hasAnyRole(user, ['funcionario']);
}

export function getHomePath(user: User | null): string {
  if (isAdmin(user)) {
    return '/admin/dashboard';
  }

  if (isSecretary(user)) {
    return '/admin/dashboard';
  }

  if (isFuncionario(user)) {
    return '/app/inicio';
  }

  return '/login';
}

export function getPrimaryRole(user: User | null): Role | null {
  return user?.roles[0] ?? null;
}
