import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CreditCard, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Alert } from '../../../components/ui/Alert';
import { Badge } from '../../../components/ui/Badge';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Switch } from '../../../components/ui/Switch';
import { getErrorMessage } from '../../../lib/utils/errors';
import { configuracionService } from '../services/configuracion.service';

export function ConfiguracionCertificacionesPage() {
  const client = useQueryClient();
  const [pendingValue, setPendingValue] = useState<boolean | null>(null);
  const query = useQuery({ queryKey: ['configuracion-certificaciones'], queryFn: configuracionService.get });
  const mutation = useMutation({
    mutationFn: configuracionService.update,
    onSuccess: () => { setPendingValue(null); client.invalidateQueries({ queryKey: ['configuracion-certificaciones'] }); },
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const config = query.data.data;
  const activating = pendingValue === true;

  return (
    <div className="app-page max-w-4xl">
      <PageHeader eyebrow="Configuración institucional" title="Pago de certificaciones" description="Controle si la expedición automática debe esperar una confirmación de pago." />
      {mutation.isError ? <ErrorState message={getErrorMessage(mutation.error)} /> : null}
      <section className="surface-section overflow-hidden">
        <div className="flex flex-col gap-6 p-6 sm:flex-row sm:items-start sm:justify-between sm:p-8">
          <div className="flex max-w-2xl gap-4">
            <span className={`h-fit rounded-lg p-3 ${config.requiere_pago_certificado ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success'}`}>{config.requiere_pago_certificado ? <CreditCard size={25} /> : <ShieldCheck size={25} />}</span>
            <div><div className="flex flex-wrap items-center gap-3"><h2 className="text-lg font-bold text-text">Exigir pago para certificaciones</h2><Badge tone={config.requiere_pago_certificado ? 'gold' : 'green'}>{config.requiere_pago_certificado ? 'ACTIVADO' : 'DESACTIVADO'}</Badge></div><p className="mt-2 text-sm leading-6 text-muted">{config.descripcion}</p></div>
          </div>
          <Switch checked={config.requiere_pago_certificado} description="Cambiar esta opción afecta las nuevas solicitudes." disabled={mutation.isPending} label="Exigir pago para certificaciones" onChange={setPendingValue} />
        </div>
        <div className="border-t border-border bg-surface-muted/45 p-6 sm:px-8">
          <Alert title="Impacto operativo" tone="info">Al activar el pago, las nuevas solicitudes crean una orden pendiente y no generan el PDF hasta que el backend reciba una confirmación válida. No existe aprobación humana en este flujo.</Alert>
        </div>
      </section>
      <ConfirmDialog confirmLabel={activating ? 'Activar pago' : 'Desactivar pago'} description={activating ? 'Las nuevas solicitudes quedarán pendientes hasta recibir confirmación de pago.' : 'Las nuevas solicitudes elegibles volverán a generar el certificado de inmediato.'} disabled={mutation.isPending} onCancel={() => setPendingValue(null)} onConfirm={() => pendingValue !== null && mutation.mutate(pendingValue)} open={pendingValue !== null} title={activating ? 'Activar pago de certificaciones' : 'Desactivar pago de certificaciones'} />
    </div>
  );
}
