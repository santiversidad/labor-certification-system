import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, FileText, ListChecks, Send } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Textarea } from '../../../components/ui/Textarea';
import { formatLongDate } from '../../../lib/formatters/dates';
import { getErrorMessage } from '../../../lib/utils/errors';
import { solicitudesService } from '../services/solicitudes.service';
import type { TipoCertificado } from '../types/solicitud.types';
import { CERTIFICATE_TYPE_DESCRIPTIONS, CERTIFICATE_TYPE_LABELS } from '../utils/certificateType';

export function SolicitudForm({ tipo }: { tipo: TipoCertificado }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [observaciones, setObservaciones] = useState('');
  const disponibilidadQuery = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad, refetchOnMount: 'always' });
  const estado = disponibilidadQuery.data?.data[tipo];
  const puedeSolicitar = estado?.puede_solicitar === true && !disponibilidadQuery.isFetching;
  const mutation = useMutation({
    mutationFn: solicitudesService.create,
    onError: () => { void queryClient.invalidateQueries({ queryKey: ['disponibilidad-certificacion'] }); },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['disponibilidad-certificacion'] });
      navigate('/app/solicitudes/confirmacion', { replace: true, state: response.data });
    },
  });
  const mutationMessage = mutation.isError ? getErrorMessage(mutation.error) : '';
  const visibleMutationMessage = tipo === 'sencillo' && /manual|ficha|funcion/i.test(mutationMessage)
    ? 'No fue posible validar la vinculación laboral. Comuníquese con Talento Humano.'
    : mutationMessage;
  const Icon = tipo === 'sencillo' ? FileText : ListChecks;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!puedeSolicitar || mutation.isPending || observaciones.length > 500) return;
    mutation.mutate({ tipo_certificado: tipo, observaciones });
  }

  return (
    <form className="surface-section max-w-3xl p-5 sm:p-8" onSubmit={submit}>
      <div className="flex items-start gap-4 rounded-lg border border-primary/20 bg-primary/5 p-4 sm:p-5">
        <span className="shrink-0 rounded-lg bg-surface p-2.5 text-primary"><Icon aria-hidden="true" size={24} /></span>
        <div className="min-w-0"><p className="eyebrow">Tipo seleccionado</p><h2 className="mt-1 text-xl font-bold text-text">{CERTIFICATE_TYPE_LABELS[tipo]}</h2><p className="mt-2 text-sm leading-6 text-text/85">{CERTIFICATE_TYPE_DESCRIPTIONS[tipo]}</p></div>
      </div>

      <div className="mt-6 space-y-5">
        {disponibilidadQuery.isError ? <Alert title="No fue posible consultar la disponibilidad" variant="error">Intente de nuevo antes de generar el certificado.</Alert> : null}
        {estado?.puede_solicitar === false ? <Alert title="Certificado no disponible actualmente" variant="warning">{estado.proxima_fecha_disponible ? <>Próxima disponibilidad: <strong>{formatLongDate(estado.proxima_fecha_disponible)}</strong>.</> : 'La próxima fecha está pendiente de confirmación.'} <Link className="font-semibold text-primary underline focus-visible:outline-2 focus-visible:outline-offset-2" to="/app/inicio">Volver al inicio</Link></Alert> : null}
        {estado?.puede_solicitar === true ? <Alert title="Antes de generar" variant="info">Al generar este certificado utilizará la disponibilidad de este tipo para el mes actual. El sistema validará su información laboral{tipo === 'funciones' ? ' y la ficha del Manual' : ''}. Si se exige pago, se creará una orden pendiente.</Alert> : null}
        <Textarea label="Observaciones (opcional)" maxLength={500} onChange={(event) => setObservaciones(event.target.value)} value={observaciones} />
        {mutation.isError ? <Alert title="No fue posible expedir la certificación" variant="error">{visibleMutationMessage}</Alert> : null}
      </div>

      <div className="mt-7 flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-between">
        <Button icon={<ArrowLeft aria-hidden="true" size={16} />} onClick={() => navigate('/app/inicio')} type="button" variant="secondary">Volver al inicio</Button>
        <Button disabled={!puedeSolicitar || mutation.isPending} icon={<Send aria-hidden="true" size={16} />} type="submit">{mutation.isPending ? 'Generando...' : `Generar certificado ${tipo === 'sencillo' ? 'sencillo' : 'con funciones'}`}</Button>
      </div>
    </form>
  );
}
