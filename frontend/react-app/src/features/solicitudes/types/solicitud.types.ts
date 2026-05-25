export type SolicitudEstado =
  | 'pendiente'
  | 'en_revision'
  | 'pendiente_pago'
  | 'pago_en_revision'
  | 'aprobada'
  | 'rechazada'
  | 'certificado_generado'
  | 'cerrada';

export type SolicitudCertificacion = {
  id: string;
  funcionarioId: string;
  radicado: string;
  tipo: 'laboral' | 'salarial' | 'funciones';
  incluyeSalario?: boolean;
  estado: SolicitudEstado;
  fechaSolicitud: string;
  observaciones?: string;
};
