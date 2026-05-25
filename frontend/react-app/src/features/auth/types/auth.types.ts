import type { User } from '../../../types/common.types';

export type LoginCredentials = {
  email: string;
  password: string;
};

export type LoginResponse = {
  token: string;
  user: User;
};
