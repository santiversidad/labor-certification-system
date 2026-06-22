export type RangoSalarial = {
  id: number;
  codigo: string;
  grado: string;
  vigencia_anio: number;
  salario_basico: number | string;
  moneda: string;
  observaciones?: string | null;
  estado: boolean;
  created_at?: string;
  updated_at?: string;
};
