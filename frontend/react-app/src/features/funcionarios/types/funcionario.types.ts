import type { Cargo } from '../../cargos/types/cargo.types';

export type Funcionario = {
  id: number;
  user_id?: number | null;
  tipo_documento: 'CC' | 'CE' | 'PA' | 'TI';
  numero_documento: string;
  nombres: string;
  apellidos: string;
  nombre_completo?: string;
  correo_institucional?: string | null;
  telefono?: string | null;
  estado: 'activo' | 'retirado' | 'suspendido';
  fecha_ingreso?: string | null;
  fecha_retiro?: string | null;
  dependencia?: string | null;
  cargo?: Cargo;
  created_at?: string;
  updated_at?: string;
};
