import { Alert } from '../../../components/ui/Alert';
import type { SolicitudEstado } from '../../solicitudes/types/solicitud.types';

export function PaymentStatusNotice({ estado, motivo }: { estado: SolicitudEstado; motivo?: string | null }) {
  if (estado === 'pendiente_pago') return <Alert title="Pago pendiente" variant="warning">Adjunte su comprobante para que Secretaría valide el pago. Su solicitud ya reservó el cupo de este tipo.</Alert>;
  if (estado === 'pago_en_revision') return <Alert title="Pago en revisión" variant="info">Secretaría está revisando el comprobante. Puede consultar el estado de esta misma solicitud.</Alert>;
  if (estado === 'pago_rechazado') return <Alert title="Pago no validado" variant="error">Motivo: <strong className="whitespace-pre-wrap break-words">{motivo}</strong>. Adjunte un nuevo comprobante en esta solicitud.</Alert>;
  if (estado === 'pago_confirmado') return <Alert title="Pago confirmado" variant="success">El sistema está procesando la certificación.</Alert>;
  if (estado === 'expedicion_fallida') return <Alert title="Error de expedición" variant="warning">El pago está confirmado. La expedición requiere un reintento técnico. Contacte a soporte e indique el radicado.</Alert>;
  if (estado === 'generada') return <Alert title="Certificado generado" variant="success">Su certificado está listo para descargar.</Alert>;
  return null;
}
