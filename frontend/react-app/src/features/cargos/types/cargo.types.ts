export type Cargo = {
  id: number;
  codigo: string;
  grado: string;
  denominacion: string;
  nivel?: string | null;
  dependencia?: string | null;
  estado: boolean;
  created_at?: string;
  updated_at?: string;
};
