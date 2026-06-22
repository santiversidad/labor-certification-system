import type { Role } from '../../types/roles.types';
import type { User } from '../../types/common.types';

export function hasAnyRole(user: User | null, allowedRoles: Role[]): boolean {
  if (!user) {
    return false;
  }

  return user.roles.some((role) => allowedRoles.includes(role));
}

export function isAdmin(user: User | null): boolean {
  return Boolean(user?.roles.includes('admin'));
}

// Admin y secretario comparten el panel de gestión /admin
export function isGestorRole(user: User | null): boolean {
  return Boolean(user?.roles.some((r) => r === 'admin' || r === 'secretario'));
}

export function getPrimaryRole(user: User | null): Role | null {
  return user?.roles[0] ?? null;
}
