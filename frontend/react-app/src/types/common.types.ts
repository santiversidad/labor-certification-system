import type { Role } from './roles.types';
import type { Funcionario } from '../features/funcionarios/types/funcionario.types';

export type ID = number;

export type User = {
  id: ID;
  name: string;
  documento: string;
  telefono?: string | null;
  estado: boolean;
  roles: Role[];
  permisos: string[];
  funcionario?: Funcionario;
  created_at?: string;
};

export type Session = {
  token: string;
  user: User;
};
