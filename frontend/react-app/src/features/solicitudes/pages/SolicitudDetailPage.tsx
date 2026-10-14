import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { formatDate } from '../../../lib/formatters/dates';
import { getErrorMessage } from '../../../lib/utils/errors';
import { PaymentStatusNotice } from '../../pagos/components/PaymentStatusNotice';
import { ReceiptHistory } from '../../pagos/components/ReceiptHistory';
import { ReceiptUploadForm } from '../../pagos/components/ReceiptUploadForm';
import { paymentWorkflowService } from '../../pagos/services/paymentWorkflow.service';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';
import { certificateTypeLabel } from '../utils/certificateType';

export function SolicitudDetailPage() {
  const { id = '' } = useParams();
  const [error, setError] = useState('');
  const [downloading, setDownloading] = useState(false);
  const query = useQuery({ queryKey: ['solicitud', id], queryFn: () => solicitudesService.getById(id), enabled: Boolean(id), refetchInterval: 15000 });
  const availability = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const s = query.data.data;
  async function descargar() {
    setError(''); setDownloading(true);
    try { await paymentWorkflowService.download(`/api/v1/solicitudes/${s.id}/certificado`); }
    catch (cause) { setError(getErrorMessage(cause)); } finally { setDownloading(false); }
  }
  return <div className="app-page max-w-4xl">
    <PageHeader title={`Solicitud ${s.radicado}`} description={`${certificateTypeLabel(s.tipo_certificado)} · ${s.created_at ? formatDate(s.created_at) : ''}`} actions={<SolicitudStatusBadge estado={s.estado} />} />
    <PaymentStatusNotice estado={s.estado} motivo={s.motivo_rechazo} />
    <div className="flex flex-wrap items-center gap-4"><Link className="font-semibold text-primary underline" to="/app/solicitudes">Mis solicitudes</Link><Button onClick={() => void query.refetch()} type="button" variant="secondary">Actualizar estado</Button>
      {s.estado === 'generada' ? <Button disabled={downloading} onClick={() => void descargar()} type="button">{downloading ? 'Descargando...' : 'Descargar PDF'}</Button> : null}</div>
    {error ? <Alert title="No fue posible descargar" variant="error">{error}</Alert> : null}
    {s.orden_pago ? <p className="break-words text-sm">Referencia interna de solicitud: <strong>{s.orden_pago.referencia}</strong></p> : null}
    {s.requiere_pago && ['pendiente_pago', 'pago_rechazado'].includes(s.estado) ? <ReceiptUploadForm id={s.id} rejected={s.estado === 'pago_rechazado'} maxKb={availability.data?.data.comprobante_max_kb} /> : null}
    {s.requiere_pago ? <ReceiptHistory comprobantes={s.pago_soporte?.comprobantes ?? []} /> : null}
  </div>;
}
