import { useEffect, useState } from 'react';
import { authStorage } from '../../../lib/auth/authStorage';
import type { Session } from '../../../types/common.types';

export function useAuth() {
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

  return {
    session,
    user: session?.user ?? null,
    token: session?.token ?? null,
    isAuthenticated: Boolean(session?.token && session.user),
    logout: authStorage.clearSession,
  };
}
