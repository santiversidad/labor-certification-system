import { useQuery } from '@tanstack/react-query';
import { CalendarClock, CheckCircle2, FileText, ListChecks } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Skeleton } from '../../../components/ui/Skeleton';
import { Badge } from '../../../components/ui/Badge';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { SpotlightCard } from '../../../components/react-bits/SpotlightCard/SpotlightCard';
import { formatLongDate } from '../../../lib/formatters/dates';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import type { DisponibilidadModalidad } from '../../solicitudes/types/solicitud.types';
import { CERTIFICATE_TYPE_DESCRIPTIONS, CERTIFICATE_TYPE_LABELS } from '../../solicitudes/utils/certificateType';
import type { TipoCertificado } from '../../solicitudes/types/solicitud.types';

type OptionProps = {
  tipo: TipoCertificado;
  icon: LucideIcon;
  availability: DisponibilidadModalidad;
  note: string;
};

function CertificationOption({ tipo, icon: Icon, availability, note }: OptionProps) {
  const navigate = useNavigate();
  const available = availability.puede_solicitar;
  return (
    <SpotlightCard disabled={!available} className={`flex min-h-72 flex-col justify-between rounded-lg border bg-surface p-5 shadow-soft transition-[border-color,box-shadow] duration-200 sm:p-6 ${available ? 'border-border hover:border-primary/40 hover:shadow-institutional focus-within:border-primary/40' : 'border-border/80 bg-surface-muted/40'}`}>
      <div>
        <div className="flex items-start justify-between gap-4">
          <span className="rounded-lg bg-primary/8 p-3 text-primary"><Icon aria-hidden="true" size={24} /></span>
          <Badge tone={available ? 'green' : 'gray'}><span className="inline-flex items-center gap-1.5">{available ? <CheckCircle2 aria-hidden="true" size={13} /> : <CalendarClock aria-hidden="true" size={13} />}{available ? 'Disponible ahora' : 'No disponible'}</span></Badge>
        </div>
        <h2 className="mt-6 text-xl font-bold tracking-tight text-text">{CERTIFICATE_TYPE_LABELS[tipo]}</h2>
        <p className="mt-2 text-sm leading-6 text-muted">{CERTIFICATE_TYPE_DESCRIPTIONS[tipo]}</p>
        <p className="mt-4 flex items-start gap-2 text-sm leading-5 text-text"><CheckCircle2 aria-hidden="true" className="mt-0.5 shrink-0 text-success" size={15} />{note}</p>
        {!available ? <div className="mt-5 rounded-md border border-warning/25 bg-amber-50 p-4 text-text"><div className="flex items-center gap-2 text-sm font-semibold"><CalendarClock aria-hidden="true" className="text-warning" size={17} />Próxima disponibilidad</div><p className="mt-1 pl-6 text-base font-bold">{availability.proxima_fecha_disponible ? formatLongDate(availability.proxima_fecha_disponible) : 'Fecha por confirmar'}</p></div> : null}
      </div>
      <Button className="mt-6 w-full" disabled={!available} onClick={() => navigate(`/app/solicitudes/nueva/${tipo}`)} type="button">Solicitar {tipo === 'sencillo' ? 'sencillo' : 'con funciones'}</Button>
    </SpotlightCard>
  );
}

export function FuncionarioDashboardPage() {
  const query = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });

  return (
    <div className="app-page max-w-6xl">
      <PageHeader eyebrow="Autoservicio 24/7" title="Certificaciones laborales" description="Seleccione el contenido que necesita. El sistema validará sus datos y expedirá el documento automáticamente cuando corresponda." />
      <Alert title="Disponibilidad mensual de certificados" variant="info">Puede generar un certificado sencillo y uno con funciones cada mes. Si ya solicitó alguno, le indicaremos cuándo volverá a estar disponible.</Alert>
      {query.isError ? <ErrorState message="No fue posible consultar la disponibilidad de certificaciones." /> : null}
      {query.isLoading ? <div className="grid gap-5 md:grid-cols-2" aria-label="Cargando tipos de certificado"><Skeleton className="h-72" /><Skeleton className="h-72" /></div> : null}
      {query.data ? (
        <div className="grid gap-5 md:grid-cols-2">
          <CertificationOption availability={query.data.data.sencillo} icon={FileText} note="Se valida su vinculación laboral vigente." tipo="sencillo" />
          <CertificationOption availability={query.data.data.funciones} icon={ListChecks} note="Se valida la ficha asignada antes de generar." tipo="funciones" />
        </div>
      ) : null}
      <p className="text-xs text-muted">Este portal no permite editar expedientes, fichas ni funciones institucionales.</p>
    </div>
  );
}
