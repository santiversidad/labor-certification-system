import type { Role } from './roles.types';
import type { Funcionario } from '../features/funcionarios/types/funcionario.types';

export type ID = number;

export type User = {
  id: ID;
  name: string;
  documento: string | null;
  username: string;
  tipo_cuenta: 'funcionario' | 'backoffice' | 'legacy';
  funcionario_id?: number | null;
  email?: string | null;
  last_login_at?: string | null;
  desactivado_at?: string | null;
  motivo_desactivacion?: string | null;
  telefono?: string | null;
  estado: boolean;
  must_change_password: boolean;
  mfa_enabled?: boolean;
  mfa_pending?: boolean;
  password_changed_at?: string | null;
  roles: Role[];
  permisos: string[];
  funcionario?: Funcionario;
  created_at?: string;
};

export type Session = {
  user: User;
};
