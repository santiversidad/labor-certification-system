export type RangoSalarial = {
  id: string;
  cargoId: string;
  grado: string;
  salarioBase: number;
  vigenciaDesde: string;
  vigenciaHasta?: string;
  activo: boolean;
};
