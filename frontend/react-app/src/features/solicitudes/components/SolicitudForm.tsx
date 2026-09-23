import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, FileText, ListChecks, Send } from 'lucide-react';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Textarea } from '../../../components/ui/Textarea';
import { getErrorMessage } from '../../../lib/utils/errors';
import { solicitudSchema, type SolicitudFormValues } from '../schemas/solicitud.schema';
import { solicitudesService } from '../services/solicitudes.service';
import { CERTIFICATE_TYPE_DESCRIPTIONS, CERTIFICATE_TYPE_LABELS } from '../utils/certificateType';
import type { TipoCertificado } from '../types/solicitud.types';

const steps = ['Seleccionar', 'Resumen', 'Confirmar', 'Resultado'];

export function SolicitudForm() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const requestedMode = searchParams.get('modalidad');
  const [screen, setScreen] = useState<'select' | 'summary'>('select');
  const { register, handleSubmit, control, formState: { errors } } = useForm<SolicitudFormValues>({
    resolver: zodResolver(solicitudSchema),
    defaultValues: {
      tipo_certificado: requestedMode === 'funciones' ? 'funciones' : 'sencillo',
      observaciones: '',
    },
  });
  const tipoCertificado = useWatch({ control, name: 'tipo_certificado' });
  const observaciones = useWatch({ control, name: 'observaciones' });
  const disponibilidadQuery = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });
  const disponibilidad = disponibilidadQuery.data?.data;
  const puedeSolicitar = disponibilidad?.[tipoCertificado].puede_solicitar;
  const mutation = useMutation({
    mutationFn: solicitudesService.create,
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['disponibilidad-certificacion'] });
      navigate('/app/solicitudes/confirmacion', { replace: true, state: response.data });
    },
  });
  const mutationMessage = mutation.isError
    ? getErrorMessage(mutation.error)
    : '';
  const visibleMutationMessage = tipoCertificado === 'sencillo' && /manual|ficha|funcion/i.test(mutationMessage)
    ? 'No fue posible validar la vinculación laboral. Comuníquese con Talento Humano.'
    : mutationMessage;

  return (
    <form className="surface-section overflow-hidden" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
      <ol aria-label="Progreso de la solicitud" className="grid grid-cols-4 border-b border-border bg-surface-muted/55 px-4 py-4 sm:px-7">
        {steps.map((step, index) => {
          const active = screen === 'select' ? index === 0 : index === 1 || (mutation.isPending && index === 2);
          const completed = screen === 'summary' && index === 0;
          return <li className={`flex items-center gap-2 text-xs font-semibold ${active ? 'text-primary' : completed ? 'text-success' : 'text-muted'}`} key={step}><span className={`grid h-6 w-6 shrink-0 place-items-center rounded-full border ${active ? 'border-primary bg-primary text-white' : completed ? 'border-success bg-success text-white' : 'border-border bg-surface'}`}>{completed ? <Check size={14} /> : index + 1}</span><span className="hidden sm:inline">{step}</span></li>;
        })}
      </ol>

      <div className="p-5 sm:p-8">
        {screen === 'select' ? (
          <div className="space-y-7">
            <div><p className="eyebrow">Paso 1 de 4</p><h2 className="mt-2 text-xl font-bold text-text">Seleccione la certificación</h2><p className="mt-1 text-sm text-muted">Defina si necesita información laboral sencilla o las funciones del Manual.</p></div>
            <fieldset>
              <legend className="text-sm font-semibold text-text">Tipo de certificado</legend>
              <p className="mt-1 text-sm text-muted">Las funciones se obtienen de la ficha asociada por Talento Humano; no se seleccionan manualmente.</p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                {([
                  ['sencillo', FileText],
                  ['funciones', ListChecks],
                ] as const).map(([tipo, Icon]) => {
                  const estado = disponibilidad?.[tipo];
                  return (
                    <label className="relative cursor-pointer rounded-lg border border-border bg-surface p-5 transition hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary has-[:focus-visible]:ring-offset-2" key={tipo}>
                      <input className="sr-only" type="radio" value={tipo} {...register('tipo_certificado')} />
                      <span className="flex items-start gap-3"><Icon aria-hidden="true" className="mt-0.5 shrink-0 text-primary" size={22} /><span><strong className="block text-text">{CERTIFICATE_TYPE_LABELS[tipo]}</strong><span className="mt-1 block text-sm leading-6 text-muted">{CERTIFICATE_TYPE_DESCRIPTIONS[tipo]}</span></span></span>
                      <span className={`mt-4 block text-xs font-semibold ${estado?.puede_solicitar === false ? 'text-warning' : 'text-success'}`}>{estado?.puede_solicitar === false ? `Disponible nuevamente: ${estado.proxima_fecha_disponible}` : 'Disponible este mes'}</span>
                    </label>
                  );
                })}
              </div>
            </fieldset>
            <Textarea error={errors.observaciones?.message} label="Observaciones (opcional)" maxLength={500} {...register('observaciones')} />
            {puedeSolicitar === false ? <Alert title="Ya solicitó este tipo" tone="warning">Podrá solicitarlo nuevamente en el siguiente mes calendario.</Alert> : null}
            <div className="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-between">
              <Link to="/app/inicio"><Button icon={<ArrowLeft size={16} />} type="button" variant="secondary">Volver</Button></Link>
              <Button disabled={disponibilidadQuery.isLoading || puedeSolicitar === false} onClick={() => setScreen('summary')} type="button">Continuar</Button>
            </div>
          </div>
        ) : (
          <div className="space-y-7">
            <div><p className="eyebrow">Pasos 2 y 3 de 4</p><h2 className="mt-2 text-xl font-bold text-text">Revise y confirme</h2><p className="mt-1 text-sm text-muted">La generación comenzará inmediatamente después de confirmar.</p></div>
            <dl className="divide-y divide-border rounded-lg border border-border">
              <SummaryRow label="Tipo" value={CERTIFICATE_TYPE_LABELS[tipoCertificado as TipoCertificado]} />
              <SummaryRow label="Observaciones" value={observaciones?.trim() || 'Sin observaciones'} />
            </dl>
            <Alert title="Antes de confirmar" tone="info">El sistema validará el cupo mensual y la asignación laboral. Para certificados con funciones también validará la ficha del Manual. Si la configuración exige pago, creará una orden pendiente.</Alert>
            {mutation.isError ? <Alert title="No fue posible expedir la certificación" tone="error">{visibleMutationMessage}</Alert> : null}
            <div className="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-between">
              <Button disabled={mutation.isPending} icon={<ArrowLeft size={16} />} onClick={() => setScreen('select')} type="button" variant="secondary">Modificar</Button>
              <Button disabled={mutation.isPending || puedeSolicitar === false} icon={<Send size={16} />} type="submit">{mutation.isPending ? 'Generando...' : 'Confirmar y generar'}</Button>
            </div>
          </div>
        )}
      </div>
    </form>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return <div className="grid gap-1 px-4 py-3.5 sm:grid-cols-[160px_1fr]"><dt className="text-sm font-medium text-muted">{label}</dt><dd className="text-sm font-semibold text-text">{value}</dd></div>;
}
