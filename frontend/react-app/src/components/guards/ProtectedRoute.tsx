import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../features/auth/hooks/useAuth';
import { ErrorState } from '../feedback/ErrorState';
import { LoadingState } from '../feedback/LoadingState';

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const location = useLocation();
  const { isAuthenticated, isValidating, token, user, validationError } = useAuth();

  if (isValidating) {
    return <LoadingState label="Validando sesión..." />;
  }

  if (token && validationError) {
    return <ErrorState message="No fue posible validar la sesión. Intente nuevamente." />;
  }

  if (!isAuthenticated) {
    return <Navigate replace state={{ from: location }} to="/login" />;
  }

  if (user?.must_change_password && location.pathname !== '/cambiar-contrasena') {
    return <Navigate replace to="/cambiar-contrasena" />;
  }

  return <>{children}</>;
}
