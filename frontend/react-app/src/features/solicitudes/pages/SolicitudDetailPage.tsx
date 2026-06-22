import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, CreditCard, FileCheck, X } from 'lucide-react';
import { useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { Modal } from '../../../components/ui/Modal';
import { PageHeader } from '../../../components/ui/PageHeader';
import { formatDate } from '../../../lib/formatters/dates';
import { getErrorMessage } from '../../../lib/utils/errors';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';

const tipoLabels: Record<string, string> = {
  laboral: 'Certificación laboral',
  funciones: 'Certificación de funciones',
  salario: 'Certificación de salario',
  laboral_salario: 'Certificación laboral con salario',
};

export function SolicitudDetailPage() {
  const { id = '' } = useParams();
  const queryClient = useQueryClient();
  const [rejectOpen, setRejectOpen] = useState(false);
  const [confirmAction, setConfirmAction] = useState<'aprobar' | 'pendiente_pago' | 'generar' | null>(null);
  const [observacion, setObservacion] = useState('');
  const [rejectError, setRejectError] = useState('');

  const { data, isLoading, isError } = useQuery({
    queryKey: ['solicitud', id],
    queryFn: () => solicitudesService.getById(id),
    enabled: Boolean(id),
  });

  const invalidateSolicitud = () => {
    void queryClient.invalidateQueries({ queryKey: ['solicitud', id] });
    void queryClient.invalidateQueries({ queryKey: ['solicitudes'] });
  };

  const approveMutation = useMutation({ mutationFn: () => solicitudesService.approve(id), onSuccess: invalidateSolicitud });
  const rejectMutation = useMutation({ mutationFn: () => solicitudesService.reject(id, observacion), onSuccess: invalidateSolicitud });
  const paymentMutation = useMutation({ mutationFn: () => solicitudesService.markPaymentPending(id), onSuccess: invalidateSolicitud });
  const generateMutation = useMutation({ mutationFn: () => solicitudesService.generateCertificate(id), onSuccess: invalidateSolicitud });
  const activeMutation = approveMutation.isPending || rejectMutation.isPending || paymentMutation.isPending || generateMutation.isPending;

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  const solicitud = data.data;

  const canApprove = !['aprobado', 'rechazado', 'generado', 'cancelado'].includes(solicitud.estado);
  const canReject = !['rechazado', 'generado', 'cancelado'].includes(solicitud.estado);
  const canMarkPayment = solicitud.requiere_pago && solicitud.estado === 'aprobado';
  const canGenerate = solicitud.estado === 'pago_validado' || (solicitud.estado === 'aprobado' && !solicitud.requiere_pago);

  function handleReject() {
    if (!observacion.trim()) {
      setRejectError('La observacion es obligatoria para rechazar la solicitud.');
      return;
    }

    setRejectError('');
    rejectMutation.mutate(undefined, {
      onSuccess: () => {
        setRejectOpen(false);
        setObservacion('');
      },
    });
  }

  function handleConfirmAction() {
    if (confirmAction === 'aprobar') {
      approveMutation.mutate(undefined, { onSuccess: () => setConfirmAction(null) });
    }

    if (confirmAction === 'pendiente_pago') {
      paymentMutation.mutate(undefined, { onSuccess: () => setConfirmAction(null) });
    }

    if (confirmAction === 'generar') {
      generateMutation.mutate(undefined, { onSuccess: () => setConfirmAction(null) });
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Solicitud N° ${solicitud.id}`}
        description="Revise la información del funcionario y ejecute las acciones permitidas para el estado actual."
        actions={<SolicitudStatusBadge estado={solicitud.estado} />}
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <Card title="Datos del funcionario">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div><dt className="text-muted">Funcionario</dt><dd className="font-medium text-text">{solicitud.funcionario ? `${solicitud.funcionario.nombres} ${solicitud.funcionario.apellidos}` : '—'}</dd></div>
              <div><dt className="text-muted">Documento</dt><dd className="font-medium text-text">{solicitud.funcionario?.numero_documento ?? '—'}</dd></div>
            </dl>
          </Card>

          <Card title="Datos de la solicitud">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div><dt className="text-muted">Tipo</dt><dd className="font-medium text-text">{tipoLabels[solicitud.tipo_certificado] ?? solicitud.tipo_certificado}</dd></div>
              <div><dt className="text-muted">Requiere salario</dt><dd className="font-medium text-text">{solicitud.requiere_salario ? 'Sí' : 'No'}</dd></div>
              <div><dt className="text-muted">Requiere pago</dt><dd className="font-medium text-text">{solicitud.requiere_pago ? 'Sí' : 'No'}</dd></div>
              <div><dt className="text-muted">Fecha</dt><dd className="font-medium text-text">{solicitud.created_at ? formatDate(solicitud.created_at) : '—'}</dd></div>
              <div className="sm:col-span-2"><dt className="text-muted">Observaciones</dt><dd className="font-medium text-text">{solicitud.observaciones ?? 'Sin observaciones'}</dd></div>
              {solicitud.motivo_rechazo ? (
                <div className="sm:col-span-2"><dt className="text-muted">Motivo de rechazo</dt><dd className="font-medium text-villavoRed">{solicitud.motivo_rechazo}</dd></div>
              ) : null}
            </dl>
          </Card>
        </div>

        <Card title="Acciones de revision" description="Las acciones se habilitan segun el estado de la solicitud.">
          <div className="space-y-3">
            <Button className="w-full" disabled={!canApprove || activeMutation} icon={<Check size={16} />} onClick={() => setConfirmAction('aprobar')} type="button">
              Aprobar solicitud
            </Button>
            <Button className="w-full" disabled={!canReject || activeMutation} icon={<X size={16} />} onClick={() => setRejectOpen(true)} type="button" variant="danger">
              Rechazar solicitud
            </Button>
            <Button className="w-full" disabled={!canMarkPayment || activeMutation} icon={<CreditCard size={16} />} onClick={() => setConfirmAction('pendiente_pago')} type="button" variant="secondary">
              Marcar pendiente de pago
            </Button>
            <Button className="w-full" disabled={!canGenerate || activeMutation} icon={<FileCheck size={16} />} onClick={() => setConfirmAction('generar')} type="button" variant="secondary">
              Generar certificado
            </Button>
            {approveMutation.isError || rejectMutation.isError || paymentMutation.isError || generateMutation.isError ? (
              <p className="text-sm text-villavoRed">
                {getErrorMessage(approveMutation.error ?? rejectMutation.error ?? paymentMutation.error ?? generateMutation.error)}
              </p>
            ) : null}
          </div>
        </Card>
      </div>

      <ConfirmDialog
        confirmLabel={confirmAction === 'generar' ? 'Generar' : confirmAction === 'pendiente_pago' ? 'Marcar' : 'Aprobar'}
        loading={activeMutation}
        message={confirmAction === 'generar' ? 'Se solicitara la generacion del certificado.' : confirmAction === 'pendiente_pago' ? 'La solicitud quedara pendiente de pago.' : 'La solicitud quedara aprobada para continuar el proceso.'}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleConfirmAction}
        open={Boolean(confirmAction)}
        title="Confirmar accion"
      />

      <Modal onClose={() => setRejectOpen(false)} open={rejectOpen} title="Rechazar solicitud">
        <label className="space-y-1 text-sm">
          <span className="font-medium text-text">Observacion obligatoria</span>
          <textarea className="min-h-28 w-full rounded-md border border-border bg-surface px-3 py-2" value={observacion} onChange={(event) => setObservacion(event.target.value)} />
        </label>
        {rejectError ? <p className="mt-2 text-sm text-villavoRed">{rejectError}</p> : null}
        <div className="mt-5 flex justify-end gap-2">
          <Button disabled={rejectMutation.isPending} onClick={() => setRejectOpen(false)} type="button" variant="secondary">Cancelar</Button>
          <Button disabled={rejectMutation.isPending} onClick={handleReject} type="button" variant="danger">
            {rejectMutation.isPending ? 'Rechazando...' : 'Rechazar'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
