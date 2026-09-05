import type { User } from '../../../types/common.types';

export type LoginCredentials = {
  cedula: string;
  password: string;
};

export type LoginResponse = {
  token: string;
  user: User;
};

export type ChangePasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
};
