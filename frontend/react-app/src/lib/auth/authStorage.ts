import type { Session, User } from '../../types/common.types';

const SESSION_KEY = 'clv_session';

export const authStorage = {
  getSession(): Session | null {
    const rawSession = localStorage.getItem(SESSION_KEY);

    if (!rawSession) {
      return null;
    }

    try {
      return JSON.parse(rawSession) as Session;
    } catch {
      localStorage.removeItem(SESSION_KEY);
      return null;
    }
  },

  setSession(session: Session) {
    localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    window.dispatchEvent(new Event('auth-session-changed'));
  },

  clearSession() {
    localStorage.removeItem(SESSION_KEY);
    window.dispatchEvent(new Event('auth-session-changed'));
  },

  getToken(): string | null {
    return this.getSession()?.token ?? null;
  },

  getUser(): User | null {
    return this.getSession()?.user ?? null;
  },

  isAuthenticated(): boolean {
    return Boolean(this.getToken() && this.getUser());
  },
};
