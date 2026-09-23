import type { Certificado } from '../../certificados/types/certificado.types';
import type { Funcionario } from '../../funcionarios/types/funcionario.types';

export type TipoCertificado = 'sencillo' | 'funciones';
export type SolicitudEstado =
  | 'pendiente' | 'en_revision' | 'pendiente_pago' | 'pago_en_revision'
  | 'aprobada' | 'rechazada' | 'certificado_generado' | 'cerrada'
  | 'generando' | 'generada' | 'fallida';

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
};

export type DisponibilidadModalidad = {
  puede_solicitar: boolean;
  proxima_fecha_disponible: string | null;
};

export type DisponibilidadCertificacion = {
  periodo: string;
  sencillo: DisponibilidadModalidad;
  funciones: DisponibilidadModalidad;
};

export type ExpedicionCertificacion = {
  resultado: 'generada' | 'pendiente_pago';
  solicitud: SolicitudCertificacion;
  certificado?: Certificado;
  descarga_url?: string;
  descarga_expira_en?: string;
  orden_pago?: { referencia: string; estado: 'pendiente' };
};
