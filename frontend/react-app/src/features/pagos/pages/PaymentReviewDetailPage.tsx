import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Textarea } from '../../../components/ui/Textarea';
import { formatDate } from '../../../lib/formatters/dates';
import { getErrorMessage } from '../../../lib/utils/errors';
import { useAuth } from '../../auth/hooks/useAuth';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import { certificateTypeLabel } from '../../solicitudes/utils/certificateType';
import { PaymentStatusNotice } from '../components/PaymentStatusNotice';
import { ReceiptHistory } from '../components/ReceiptHistory';
import { paymentWorkflowService } from '../services/paymentWorkflow.service';

export function PaymentReviewDetailPage() {
  const { id = '' } = useParams();
  const { user } = useAuth();
  const client = useQueryClient();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [motivo, setMotivo] = useState('');
  const permitted = Boolean(user?.roles.includes('secretario') && user.permisos.includes('pagos.ver'));
  const query = useQuery({ queryKey: ['secretaria-pago', id], queryFn: () => paymentWorkflowService.detail(id), enabled: permitted });
  const mutation = useMutation({ mutationFn: (action: 'confirm' | 'reject') => action === 'confirm' ? paymentWorkflowService.confirm(Number(id)) : paymentWorkflowService.reject(Number(id), motivo.trim()), onSuccess: async () => {
    setConfirmOpen(false); setRejectOpen(false); setMotivo('');
    await client.invalidateQueries({ queryKey: ['secretaria-pago', id] });
    await client.invalidateQueries({ queryKey: ['secretaria-pagos'] });
  }, onError: () => { void query.refetch(); } });
  if (!permitted) return <Alert title="Acceso restringido" variant="error">No tiene permiso para consultar pagos.</Alert>;
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const s = query.data.data;
  const enRevision = s.estado === 'pago_en_revision';
  return <div className="app-page max-w-4xl">
    <PageHeader eyebrow="Secretaría" title={`Pago de solicitud ${s.radicado}`} description={`${s.funcionario.nombres} ${s.funcionario.apellidos} · ${s.funcionario.documento_parcial} · ${certificateTypeLabel(s.tipo_certificado)}`} actions={<SolicitudStatusBadge estado={s.estado} />} />
    <p className="text-sm">Solicitud: {s.created_at ? formatDate(s.created_at) : '—'} · Referencia interna: {s.referencia_interna ?? '—'}</p>
    <PaymentStatusNotice estado={s.estado} motivo={s.motivo_rechazo} />
    {mutation.isError ? <Alert title="No fue posible registrar la revisión" variant="error">{getErrorMessage(mutation.error)}</Alert> : null}
    <ReceiptHistory comprobantes={s.comprobantes} />
    {enRevision ? <section className="surface-section space-y-4 p-5 sm:p-7"><h2 className="text-lg font-bold">Revisión del pago</h2><p className="text-sm text-muted">Verifique el comprobante. La confirmación inicia la expedición automática con la información institucional vigente.</p><div className="flex flex-wrap gap-3">
      {user?.permisos.includes('pagos.validar') ? <Button disabled={mutation.isPending} onClick={() => setConfirmOpen(true)} type="button">Confirmar pago</Button> : null}
      {user?.permisos.includes('pagos.rechazar') ? <Button disabled={mutation.isPending} onClick={() => setRejectOpen(true)} type="button" variant="danger">Rechazar pago</Button> : null}
    </div></section> : null}
    <Link className="font-semibold text-primary underline" to="/admin/pagos">Volver a validación de pagos</Link>
    <ConfirmDialog open={confirmOpen} title="Confirmar pago" description="Confirme que verificó el pago de este comprobante. El sistema intentará expedir automáticamente el certificado." confirmLabel="Confirmar pago" disabled={mutation.isPending} onCancel={() => setConfirmOpen(false)} onConfirm={() => mutation.mutate('confirm')} />
    <ConfirmDialog open={rejectOpen} title="Rechazar pago" description="El titular verá este motivo y podrá adjuntar otro comprobante en la misma solicitud." confirmLabel="Registrar rechazo" confirmVariant="danger" disabled={mutation.isPending || motivo.trim().length === 0} onCancel={() => setRejectOpen(false)} onConfirm={() => motivo.trim() && mutation.mutate('reject')}><Textarea label="Motivo del rechazo" maxLength={1000} required value={motivo} onChange={(e) => setMotivo(e.target.value)} /></ConfirmDialog>
  </div>;
}
