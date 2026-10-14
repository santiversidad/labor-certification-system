import { Navigate } from 'react-router-dom';
import { useAuth } from '../../features/auth/hooks/useAuth';
import { getHomePath } from '../../lib/auth/permissions';

export function HomeRedirect() {
  const { user } = useAuth();
  return <Navigate replace to={getHomePath(user)} />;
}
