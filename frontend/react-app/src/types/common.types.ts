import type { Role } from './roles.types';

export type ID = string;

export type User = {
  id: ID;
  name: string;
  documento: string;
  telefono?: string | null;
  estado: boolean;
  roles: Role[];
  permisos: string[];
  funcionario?: {
    id: ID;
    correo_institucional?: string | null;
    dependencia?: string | null;
  };
  created_at?: string;
};

export type Session = {
  token: string;
  user: User;
};

export type AuditAction = 'crear' | 'actualizar' | 'validar' | 'descargar' | 'rechazar' | 'login';
