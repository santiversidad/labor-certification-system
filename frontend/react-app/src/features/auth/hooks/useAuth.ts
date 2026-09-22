import { useContext, useEffect, useState } from 'react';
import { authStorage } from '../../../lib/auth/authStorage';
import type { Session } from '../../../types/common.types';
import { AuthContext } from '../context/auth-context';

export function useAuth() {
  const context = useContext(AuthContext);
  const [session, setSession] = useState<Session | null>(() => authStorage.getSession());

  useEffect(() => {
    function syncSession() {
      setSession(authStorage.getSession());
    }

    window.addEventListener('auth-session-changed', syncSession);
    window.addEventListener('storage', syncSession);

    return () => {
      window.removeEventListener('auth-session-changed', syncSession);
      window.removeEventListener('storage', syncSession);
    };
  }, []);

  const storedAuth = {
    session,
    user: session?.user ?? null,
    token: session?.token ?? null,
    isAuthenticated: Boolean(session?.token && session.user),
    isValidating: false,
    validationError: false,
    logout: authStorage.clearSession,
    updateUser: (user: Session['user']) => {
      if (session) authStorage.setSession({ ...session, user });
    },
  };

  return context ?? storedAuth;
}
