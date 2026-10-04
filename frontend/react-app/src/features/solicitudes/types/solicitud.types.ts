import type { Certificado } from '../../certificados/types/certificado.types';
import type { Funcionario } from '../../funcionarios/types/funcionario.types';

export type TipoCertificado = 'sencillo' | 'funciones';
export type SolicitudEstado =
  | 'pendiente' | 'en_revision' | 'pendiente_pago' | 'pago_en_revision'
  | 'aprobada' | 'rechazada' | 'certificado_generado' | 'cerrada'
  | 'generando' | 'generada' | 'fallida' | 'pago_rechazado' | 'pago_confirmado' | 'expedicion_fallida';
// Payment state belongs to the request; receipt reviews have their own history.

export type SolicitudCertificacion = {
  id: number;
  radicado: string;
  tipo_certificado: TipoCertificado;
  estado: SolicitudEstado;
  requiere_pago: boolean;
  periodo_mes: string;
  observaciones?: string | null;
  motivo_rechazo?: string | null;
  created_at?: string;
  funcionario?: Funcionario;
  certificado?: Certificado | null;
  orden_pago?: { referencia: string; estado: string } | null;
  pago_soporte?: { comprobantes: ComprobantePago[] } | null;
  error_expedicion?: string | null;
};

export type DisponibilidadModalidad = {
  puede_solicitar: boolean;
  proxima_fecha_disponible: string | null;
};

export type DisponibilidadCertificacion = {
  requiere_pago?: boolean;
  comprobante_max_kb?: number;
  periodo: string;
  sencillo: DisponibilidadModalidad;
  funciones: DisponibilidadModalidad;
};

export type ComprobantePago = {
  id: number; version: number; nombre_original: string; mime_type: string; size_bytes: number;
  estado: 'en_revision' | 'rechazado' | 'confirmado'; referencia?: string | null;
  observacion_funcionario?: string | null; motivo_rechazo?: string | null;
  revisado_at?: string | null; created_at?: string;
  revisor?: { id: number; name: string } | null; archivo_url: string;
};

export type SolicitudPago = Pick<SolicitudCertificacion, 'id' | 'radicado' | 'tipo_certificado' | 'estado' | 'requiere_pago' | 'created_at' | 'motivo_rechazo' | 'error_expedicion'> & {
  funcionario: { id: number; nombres: string; apellidos: string; documento_parcial: string };
  comprobantes: ComprobantePago[]; referencia_interna?: string | null; monto?: string | null; comprobante_max_kb: number;
};

export type ExpedicionCertificacion = {
  resultado: 'generada' | 'pendiente_pago';
  solicitud: SolicitudCertificacion;
  certificado?: Certificado;
  descarga_url?: string;
  descarga_expira_en?: string;
  orden_pago?: { referencia: string; estado: 'pendiente' };
};
