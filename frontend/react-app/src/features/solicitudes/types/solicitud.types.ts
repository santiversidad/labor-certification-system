import type { Funcionario } from '../../funcionarios/types/funcionario.types';

export type TipoCertificado = 'laboral' | 'funciones' | 'salario' | 'laboral_salario';

export type SolicitudEstado =
  | 'pendiente'
  | 'en_revision'
  | 'requiere_pago'
  | 'pago_pendiente'
  | 'pago_validado'
  | 'aprobado'
  | 'rechazado'
  | 'generado'
  | 'cancelado';

export type SolicitudEvento = {
  id: string;
  titulo: string;
  fecha: string;
  descripcion?: string;
};

export type SolicitudCertificacion = {
  id: number;
  tipo_certificado: TipoCertificado;
  estado: SolicitudEstado;
  requiere_pago: boolean;
  requiere_salario: boolean;
  observaciones?: string | null;
  motivo_rechazo?: string | null;
  reviewed_at?: string | null;
  created_at?: string;
  updated_at?: string;

  // Relaciones (cargadas según el endpoint)
  funcionario?: Funcionario;
  creado_por?: { id: number; name: string; documento: string };
  revisado_por?: { id: number; name: string; documento: string };
};
