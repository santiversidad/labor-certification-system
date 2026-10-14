import { StatusBadge } from '../../../components/ui/StatusBadge';
import type { SolicitudEstado } from '../types/solicitud.types';

const solicitudEstadoLabels: Record<SolicitudEstado, string> = {
  pendiente: 'Pendiente',
  en_revision: 'En revisión',
  pendiente_pago: 'Pendiente de pago',
  pago_en_revision: 'Pago en revisión',
  pago_rechazado: 'Pago no validado',
  pago_confirmado: 'Pago confirmado',
  expedicion_fallida: 'Error de expedición',
  aprobada: 'Aprobada histórica',
  rechazada: 'Rechazada',
  certificado_generado: 'Certificado generado',
  cerrada: 'Cerrada',
  generando: 'Generando',
  generada: 'Certificado generado',
  fallida: 'Fallida',
};

const solicitudEstadoTones: Partial<Record<SolicitudEstado, 'blue' | 'green' | 'red' | 'gold'>> = {
  pendiente: 'gold',
  en_revision: 'blue',
  pendiente_pago: 'gold',
  pago_en_revision: 'blue',
  pago_rechazado: 'red',
  pago_confirmado: 'green',
  expedicion_fallida: 'red',
  aprobada: 'green',
  rechazada: 'red',
  certificado_generado: 'green',
  cerrada: 'blue',
  generando: 'blue',
  generada: 'green',
  fallida: 'red',
};

export function SolicitudStatusBadge({ estado }: { estado: SolicitudEstado }) {
  return <StatusBadge labels={solicitudEstadoLabels} status={estado} tones={solicitudEstadoTones} />;
}
