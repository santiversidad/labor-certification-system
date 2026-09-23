import { createContext } from 'react';
import type { Session, User } from '../../../types/common.types';

export type AuthContextValue = {
  session: Session | null;
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isValidating: boolean;
  validationError: boolean;
  logout: () => void;
  updateUser: (user: User) => void;
};

export const AuthContext = createContext<AuthContextValue | null>(null);
