export type SalaryYear = {
  id: number; anio: number; estado: 'borrador' | 'vigente' | 'archivada'; revision: number;
  porcentaje_referencia: string | null; vigencia_base_id: number | null;
  fuente: string; acto_administrativo: string | null; fecha_vigencia: string | null; observaciones: string | null;
};
export type SalaryItem = {
  id: number; vigencia_id: number; cargo_id: number | null; codigo: string; grado: string; denominacion: string;
  numero_cargos: number | null; asignacion_basica_mensual: string | null; revision: number;
  origen_valor: 'pendiente' | 'manual' | 'importado' | 'calculado_por_porcentaje';
};
export type SalaryTable = {
  vigencias: SalaryYear[];
  filas: { codigo: string; grado: string; denominacion: string; valores: SalaryItem[] }[];
  meta: { current_page: number; last_page: number; total: number };
};
export type IncreasePreview = {
  cantidad: number; base_revision: number;
  ejemplos: { codigo: string; grado: string; denominacion: string; valor_base: string; valor_nuevo: string }[];
};
export type YearPayload = {
  anio: number; fuente: string; vigencia_base_id?: number; base_revision?: number; porcentaje?: string; confirmado?: boolean;
};
