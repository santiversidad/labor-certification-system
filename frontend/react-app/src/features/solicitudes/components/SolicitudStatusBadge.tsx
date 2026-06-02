import { Badge } from '../../../components/ui/Badge';
import type { SolicitudEstado } from '../types/solicitud.types';

const labels: Record<SolicitudEstado, string> = {
  pendiente: 'Pendiente',
  en_revision: 'En revision',
  pendiente_pago: 'Pendiente pago',
  pago_en_revision: 'Pago en revision',
  aprobada: 'Aprobada',
  rechazada: 'Rechazada',
  certificado_generado: 'Certificado generado',
  cerrada: 'Cerrada',
};

export function SolicitudStatusBadge({ estado }: { estado: SolicitudEstado }) {
  const tone = estado === 'rechazada' ? 'red' : estado === 'certificado_generado' || estado === 'aprobada' ? 'green' : 'gold';

  return <Badge tone={tone}>{labels[estado]}</Badge>;
}
