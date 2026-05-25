import type { Role } from '../../types/roles.types';
import type { User } from '../../types/common.types';

export function hasAnyRole(user: User | null, allowedRoles: Role[]): boolean {
  if (!user) {
    return false;
  }

  return allowedRoles.includes(user.role);
}

export function isAdmin(user: User | null): boolean {
  return user?.role === 'administrador';
}
