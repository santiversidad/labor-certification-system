import { Badge } from '../../../components/ui/Badge';
import type { SolicitudEstado } from '../types/solicitud.types';

const labels: Record<SolicitudEstado, string> = {
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

export function SolicitudStatusBadge({ estado }: { estado: SolicitudEstado }) {
  const tone: 'red' | 'green' | 'gold' | 'blue' =
    estado === 'rechazado' || estado === 'cancelado'
      ? 'red'
      : estado === 'generado' || estado === 'aprobado' || estado === 'pago_validado'
      ? 'green'
      : estado === 'en_revision'
      ? 'blue'
      : 'gold';

  return <Badge tone={tone}>{labels[estado]}</Badge>;
}
