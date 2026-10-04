import type { User } from '../../../types/common.types';

export type LoginCredentials = {
  username: string;
  password: string;
};

export type LoginResponse = {
  status: 'AUTHENTICATED' | 'PASSWORD_CHANGE_REQUIRED' | 'MFA_REQUIRED' | 'MFA_ENROLLMENT_REQUIRED';
  user: User;
};

export type ChangePasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
};
