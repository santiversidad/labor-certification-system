import type { Role } from './roles.types';

export type ID = string;

export type User = {
  id: ID;
  name: string;
  email: string;
  role: Role;
  dependencia?: string;
};

export type Session = {
  token: string;
  user: User;
};

export type AuditAction = 'crear' | 'actualizar' | 'validar' | 'descargar' | 'rechazar' | 'login';
