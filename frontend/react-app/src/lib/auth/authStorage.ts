import type { Session, User } from '../../types/common.types';

// Identity is memory-only. Authentication is the server's HttpOnly cookie.
let current: Session | null = null;
export const authStorage = {
  getSession(): Session | null { return current; },
  setSession(session: Session) {
    current = { user: session.user };
    window.dispatchEvent(new Event('auth-session-changed'));
  },
  clearSession() {
    if (current === null) return;
    current = null;
    window.dispatchEvent(new Event('auth-session-changed'));
  },
  getUser(): User | null { return current?.user ?? null; },
  isAuthenticated(): boolean { return Boolean(current?.user); },
  removeLegacySession() { localStorage.removeItem('clv_session'); },
};
