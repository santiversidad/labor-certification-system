import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CreditCard, ShieldCheck } from 'lucide-react';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { configuracionService } from '../services/configuracion.service';

export function ConfiguracionCertificacionesPage() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ['configuracion-certificaciones'], queryFn: configuracionService.get });
  const mutation = useMutation({ mutationFn: configuracionService.update, onSuccess: () => client.invalidateQueries({ queryKey: ['configuracion-certificaciones'] }) });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const config = query.data.data;

  return (
    <div className="mx-auto max-w-4xl space-y-7">
      <PageHeader eyebrow="Configuración institucional" title="Pago de certificaciones" description="Controle si la expedición automática debe esperar una confirmación de pago." />
      <section className="rounded-xl border border-border bg-white p-7">
        <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex gap-4">
            <span className={`rounded-xl p-3 ${config.requiere_pago_certificado ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-villavoGreen'}`}>
              {config.requiere_pago_certificado ? <CreditCard size={26} /> : <ShieldCheck size={26} />}
            </span>
            <div><p className="text-xs font-semibold uppercase tracking-wider text-muted">Estado actual</p><h2 className="mt-1 text-xl font-semibold text-text">{config.requiere_pago_certificado ? 'ACTIVADO' : 'DESACTIVADO'}</h2><p className="mt-2 max-w-xl text-sm leading-6 text-muted">{config.descripcion}</p></div>
          </div>
          <Button disabled={mutation.isPending} onClick={() => mutation.mutate(!config.requiere_pago_certificado)} type="button" variant={config.requiere_pago_certificado ? 'secondary' : 'primary'}>
            {config.requiere_pago_certificado ? 'Desactivar cobro' : 'Activar cobro'}
          </Button>
        </div>
        <p className="mt-6 border-t border-border pt-5 text-xs leading-5 text-muted">No existe una pasarela configurada todavía. Activar el cobro crea una orden pendiente y detiene la generación; ningún botón del funcionario puede confirmar el pago.</p>
      </section>
    </div>
  );
}
