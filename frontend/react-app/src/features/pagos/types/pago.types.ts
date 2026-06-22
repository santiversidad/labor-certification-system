export type PagoEstado = 'no_requerido' | 'pendiente' | 'cargado' | 'aprobado' | 'rechazado';

export type PagoSoporte = {
  id: string;
  solicitudId: string;
  funcionarioNombre?: string;
  valor: number;
  estado: PagoEstado;
  fechaCarga?: string;
  referencia?: string;
  soporteUrl?: string;
  observacion?: string;
};
