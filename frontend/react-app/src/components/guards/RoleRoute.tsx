import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { getHomePath, hasAnyRole } from '../../lib/auth/permissions';
import type { Role } from '../../types/roles.types';
import { useAuth } from '../../features/auth/hooks/useAuth';

type RoleRouteProps = {
  allowedRoles: Role[];
  children: ReactNode;
};

export function RoleRoute({ allowedRoles, children }: RoleRouteProps) {
  const { user } = useAuth();

  if (!hasAnyRole(user, allowedRoles)) {
    return <Navigate replace to={getHomePath(user)} />;
  }

  return <>{children}</>;
}
