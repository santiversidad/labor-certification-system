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
  asignacion_actual?: { id: number; manual_cargo_version_id: number | null; ficha_manual?: { id: number; source_id: string | null; area_funcional: string; version: string } | null; tipo_vinculacion: 'planta' | 'provisional' | 'encargo' | 'temporal'; naturaleza_cargo: 'carrera_administrativa' | 'libre_nombramiento' | 'provisional' | 'encargo' } | null;
  usuario?: { id: number; estado: boolean; must_change_password: boolean } | null;
  created_at?: string;
  updated_at?: string;
};
