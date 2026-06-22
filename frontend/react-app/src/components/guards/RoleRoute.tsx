import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { hasAnyRole, isGestorRole } from '../../lib/auth/permissions';
import type { Role } from '../../types/roles.types';
import { useAuth } from '../../features/auth/hooks/useAuth';

type RoleRouteProps = {
  allowedRoles: Role[];
  children: ReactNode;
};

export function RoleRoute({ allowedRoles, children }: RoleRouteProps) {
  const { user } = useAuth();

  if (!hasAnyRole(user, allowedRoles)) {
    const fallback = isGestorRole(user) ? '/admin/dashboard' : '/app/dashboard';
    return <Navigate replace to={fallback} />;
  }

  return <>{children}</>;
}
