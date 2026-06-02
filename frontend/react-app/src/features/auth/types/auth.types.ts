import type { User } from '../../../types/common.types';

export type LoginCredentials = {
  cedula: string;
  password: string;
};

export type LoginResponse = {
  token: string;
  user: User;
};
