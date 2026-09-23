import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { authStorage } from '../../../lib/auth/authStorage';
import type { Session, User } from '../../../types/common.types';
import { authService } from '../services/auth.service';
import { AuthContext, type AuthContextValue } from './auth-context';

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [storedSession, setStoredSession] = useState<Session | null>(() => authStorage.getSession());
  const token = storedSession?.token ?? null;
  const queryKey = useMemo(() => ['auth', 'me', token] as const, [token]);
  const identity = useQuery({
    queryKey,
    queryFn: authService.me,
    enabled: Boolean(token),
    retry: false,
    refetchOnWindowFocus: false,
    staleTime: Number.POSITIVE_INFINITY,
  });

  useEffect(() => {
    const syncSession = () => {
      const nextSession = authStorage.getSession();
      setStoredSession((currentSession) => {
        if (currentSession && !nextSession) queryClient.clear();
        return nextSession;
      });
    };
    window.addEventListener('auth-session-changed', syncSession);
    window.addEventListener('storage', syncSession);

    return () => {
      window.removeEventListener('auth-session-changed', syncSession);
      window.removeEventListener('storage', syncSession);
    };
  }, [queryClient]);

  useEffect(() => {
    if (!token || !identity.data?.data) return;

    const verifiedSession = { token, user: identity.data.data };
    authStorage.setSession(verifiedSession);
  }, [identity.data, token]);

  useEffect(() => {
    const status = (identity.error as { response?: { status?: number } } | null)?.response?.status;
    if (status === 401) {
      queryClient.clear();
      authStorage.clearSession();
    }
  }, [identity.error, queryClient]);

  const logout = useCallback(() => {
    queryClient.clear();
    setStoredSession(null);
    authStorage.clearSession();
  }, [queryClient]);

  const updateUser = useCallback((user: User) => {
    if (!token) return;
    const session = { token, user };
    queryClient.setQueryData(queryKey, { success: true, data: user });
    setStoredSession(session);
    authStorage.setSession(session);
  }, [queryClient, queryKey, token]);

  const verifiedUser = identity.data?.data ?? null;
  const value = useMemo<AuthContextValue>(() => {
    const session = token && verifiedUser ? { token, user: verifiedUser } : null;

    return {
      session,
      user: verifiedUser,
      token,
      isAuthenticated: Boolean(session),
      isValidating: Boolean(token && identity.isPending),
      validationError: Boolean(token && identity.isError),
      logout,
      updateUser,
    };
  }, [identity.isError, identity.isPending, logout, token, updateUser, verifiedUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
