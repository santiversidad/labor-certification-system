import { StatusBadge } from '../../../components/ui/StatusBadge';
import type { SolicitudEstado } from '../types/solicitud.types';

const solicitudEstadoLabels: Record<SolicitudEstado, string> = {
  pendiente: 'Pendiente',
  en_revision: 'En revisión',
  pendiente_pago: 'Pendiente de pago',
  pago_en_revision: 'Pago en revisión',
  aprobada: 'Aprobada',
  rechazada: 'Rechazada',
  certificado_generado: 'Certificado generado',
  cerrada: 'Cerrada',
};

const solicitudEstadoTones: Partial<Record<SolicitudEstado, 'blue' | 'green' | 'red' | 'gold'>> = {
  pendiente: 'gold',
  en_revision: 'blue',
  pendiente_pago: 'gold',
  pago_en_revision: 'blue',
  aprobada: 'green',
  rechazada: 'red',
  certificado_generado: 'green',
  cerrada: 'red',
};

export function SolicitudStatusBadge({ estado }: { estado: SolicitudEstado }) {
  return <StatusBadge labels={solicitudEstadoLabels} status={estado} tones={solicitudEstadoTones} />;
}
