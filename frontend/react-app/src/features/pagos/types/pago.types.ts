export type PagoEstado = 'pendiente' | 'aprobado' | 'rechazado';

export type PagoSoporte = {
  id: number;
  solicitud_id: number;
  archivo_original_nombre: string;
  estado: PagoEstado;
  observaciones?: string | null;
  validado_at?: string | null;
  created_at?: string;

  // Relaciones opcionales
  validado_por?: { id: number; name: string };
  funcionario?: {
    id: number;
    nombres: string;
    apellidos: string;
    numero_documento: string;
  };
};
