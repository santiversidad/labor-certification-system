import { useQuery } from '@tanstack/react-query';
import { CalendarClock, CheckCircle2, FileText, ListChecks, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Skeleton } from '../../../components/ui/Skeleton';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import type { DisponibilidadModalidad } from '../../solicitudes/types/solicitud.types';

type OptionProps = {
  title: string;
  description: string;
  icon: LucideIcon;
  availability: DisponibilidadModalidad;
  to: string;
  note: string;
};

function CertificationOption({ title, description, icon: Icon, availability, to, note }: OptionProps) {
  const available = availability.puede_solicitar;
  return (
    <article className="group flex min-h-72 flex-col justify-between rounded-lg border border-border bg-surface p-5 shadow-soft transition duration-200 hover:-translate-y-0.5 hover:border-primary/45 hover:shadow-raised sm:p-6">
      <div>
        <div className="flex items-start justify-between gap-4">
          <span className="rounded-lg bg-primary/8 p-3 text-primary"><Icon aria-hidden="true" size={24} /></span>
          <Badge tone={available ? 'green' : 'gray'}>{available ? 'Disponible' : 'Ya solicitada este mes'}</Badge>
        </div>
        <h2 className="mt-6 text-xl font-bold tracking-tight text-text">{title}</h2>
        <p className="mt-2 text-sm leading-6 text-muted">{description}</p>
        <p className="mt-4 flex items-start gap-2 text-xs leading-5 text-muted"><CheckCircle2 className="mt-0.5 shrink-0 text-success" size={15} />{note}</p>
        {!available && availability.proxima_fecha_disponible ? <p className="mt-3 flex items-center gap-2 text-xs text-muted"><CalendarClock size={15} /> Disponible nuevamente: {availability.proxima_fecha_disponible}</p> : null}
      </div>
      {available ? <Link aria-label={`Solicitar ${title}`} className="mt-6" to={to}><Button className="w-full" type="button">Solicitar</Button></Link> : <Button className="mt-6 w-full" disabled type="button">Solicitar</Button>}
    </article>
  );
}

export function FuncionarioDashboardPage() {
  const query = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });

  return (
    <div className="app-page max-w-6xl">
      <PageHeader eyebrow="Autoservicio 24/7" title="Certificaciones laborales" description="Seleccione el contenido que necesita. El sistema validará sus datos y expedirá el documento automáticamente cuando corresponda." />
      <div className="flex items-start gap-3 border-y border-border py-4 text-sm leading-6 text-muted">
        <ShieldCheck className="mt-0.5 shrink-0 text-success" size={20} />
        <p>Dispone de un cupo sencillo y uno con funciones por mes calendario. La información proviene de las fuentes institucionales vigentes.</p>
      </div>
      {query.isError ? <ErrorState message="No fue posible consultar la disponibilidad de certificaciones." /> : null}
      {query.isLoading ? <div className="grid gap-5 md:grid-cols-2" aria-label="Cargando tipos de certificado"><Skeleton className="h-72" /><Skeleton className="h-72" /></div> : null}
      {query.data ? (
        <div className="grid gap-5 md:grid-cols-2">
          <CertificationOption availability={query.data.data.sencillo} description="Constancia de vinculación laboral y datos del empleo." icon={FileText} note="Se valida su vinculación laboral vigente." title="Certificado laboral sencillo" to="/app/solicitudes/nueva?modalidad=sencillo" />
          <CertificationOption availability={query.data.data.funciones} description="Incluye la información laboral y las funciones correspondientes al empleo según el Manual aplicable." icon={ListChecks} note="Se valida la ficha asignada antes de generar." title="Certificado laboral con funciones" to="/app/solicitudes/nueva?modalidad=funciones" />
        </div>
      ) : null}
      <p className="text-xs text-muted">Este portal no permite editar expedientes, fichas ni funciones institucionales.</p>
    </div>
  );
}
