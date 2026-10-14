import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { authStorage } from '../../../lib/auth/authStorage';
import type { Session, User } from '../../../types/common.types';
import { authService } from '../services/auth.service';
import { AuthContext, type AuthContextValue } from './auth-context';

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [session, setSession] = useState<Session | null>(() => authStorage.getSession());
  const [isValidating, setValidating] = useState(true);
  const [validationError, setValidationError] = useState(false);
  // StrictMode replays effects; share the request so an obsolete anonymous /me
  // cannot issue a late cookie/401 after the user starts authenticating.
  const bootstrap = useRef<ReturnType<typeof authService.me> | null>(null);

  useEffect(() => {
    let live = true;
    let changed = false;
    authStorage.removeLegacySession();
    const sync = () => {
      changed = true;
      queryClient.clear();
      setSession(authStorage.getSession());
      setValidating(false);
      setValidationError(false);
    };
    window.addEventListener('auth-session-changed', sync);
    bootstrap.current ??= authService.me();
    void bootstrap.current.then((response) => {
      if (live && !changed) authStorage.setSession({ user: response.data });
    }).catch((error: { response?: { status?: number } }) => {
      if (live && !changed) {
        if (error.response?.status === 401) authStorage.clearSession();
        else setValidationError(true);
      }
    }).finally(() => { if (live) setValidating(false); });
    return () => { live = false; window.removeEventListener('auth-session-changed', sync); };
  }, [queryClient]);

  const logout = useCallback(() => { queryClient.clear(); authStorage.clearSession(); }, [queryClient]);
  const updateUser = useCallback((user: User) => { authStorage.setSession({ user }); }, []);
  const value = useMemo<AuthContextValue>(() => ({
    session, user: session?.user ?? null, isAuthenticated: Boolean(session), isValidating, validationError, logout, updateUser,
  }), [session, isValidating, validationError, logout, updateUser]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
