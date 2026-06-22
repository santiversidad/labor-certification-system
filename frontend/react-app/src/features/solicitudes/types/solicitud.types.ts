export type SolicitudEstado =
  | 'pendiente'
  | 'en_revision'
  | 'pendiente_pago'
  | 'pago_en_revision'
  | 'aprobada'
  | 'rechazada'
  | 'certificado_generado'
  | 'cerrada';

export type TipoCertificado = 'laboral' | 'salarial' | 'funciones';

export type SolicitudEvento = {
  id: string;
  titulo: string;
  fecha: string;
  descripcion?: string;
};

export type SolicitudCertificacion = {
  id: string;
  funcionarioId: string;
  funcionarioNombre?: string;
  funcionarioDocumento?: string;
  cargoActual?: string;
  dependencia?: string;
  radicado: string;
  tipo: TipoCertificado;
  incluyeSalario?: boolean;
  estado: SolicitudEstado;
  fechaSolicitud: string;
  observaciones?: string;
  pagoEstado?: 'no_requerido' | 'pendiente' | 'cargado' | 'aprobado' | 'rechazado';
  eventos?: SolicitudEvento[];
};
