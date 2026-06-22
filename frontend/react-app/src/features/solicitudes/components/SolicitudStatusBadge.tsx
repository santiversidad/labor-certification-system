import { StatusBadge } from '../../../components/ui/StatusBadge';
import type { SolicitudEstado } from '../types/solicitud.types';

const solicitudEstadoLabels: Record<SolicitudEstado, string> = {
  pendiente: 'Pendiente',
  en_revision: 'En revisión',
  requiere_pago: 'Requiere pago',
  pago_pendiente: 'Pago pendiente',
  pago_validado: 'Pago validado',
  aprobado: 'Aprobado',
  rechazado: 'Rechazado',
  generado: 'Generado',
  cancelado: 'Cancelado',
};

const solicitudEstadoTones: Partial<Record<SolicitudEstado, 'blue' | 'green' | 'red' | 'gold'>> = {
  pendiente: 'gold',
  en_revision: 'blue',
  requiere_pago: 'gold',
  pago_pendiente: 'gold',
  pago_validado: 'green',
  aprobado: 'green',
  rechazado: 'red',
  generado: 'green',
  cancelado: 'red',
};

export function SolicitudStatusBadge({ estado }: { estado: SolicitudEstado }) {
  return <StatusBadge labels={solicitudEstadoLabels} status={estado} tones={solicitudEstadoTones} />;
}
