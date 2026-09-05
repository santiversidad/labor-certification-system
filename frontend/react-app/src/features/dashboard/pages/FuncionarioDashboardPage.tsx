import { useQuery } from '@tanstack/react-query';
import { Banknote, CalendarClock, FileText, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import type { DisponibilidadModalidad } from '../../solicitudes/types/solicitud.types';

function Opcion({ conSalario, disponibilidad }: { conSalario: boolean; disponibilidad: DisponibilidadModalidad }) {
  const Icon = conSalario ? Banknote : FileText;
  const titulo = conSalario ? 'Certificación laboral CON salario' : 'Certificación laboral SIN salario';
  return (
    <article className="group flex min-h-64 flex-col justify-between rounded-xl border border-border bg-white p-6 transition duration-200 hover:-translate-y-0.5 hover:border-govBlue hover:shadow-lg hover:shadow-blue-950/5">
      <div>
        <div className="flex items-center justify-between">
          <span className="rounded-lg bg-blue-50 p-3 text-govBlue"><Icon size={25} /></span>
          <span className={`text-xs font-semibold uppercase tracking-wide ${disponibilidad.puede_solicitar ? 'text-villavoGreen' : 'text-muted'}`}>
            {disponibilidad.puede_solicitar ? 'Disponible este mes' : 'Ya utilizada este mes'}
          </span>
        </div>
        <h2 className="mt-7 text-2xl font-semibold text-text">{titulo}</h2>
        <p className="mt-2 text-sm leading-6 text-muted">
          {conSalario ? 'Incluye el salario institucional vigente asociado a su cargo.' : 'Certifica su vinculación sin exponer valores salariales.'}
        </p>
        {!disponibilidad.puede_solicitar && disponibilidad.proxima_fecha_disponible ? (
          <p className="mt-4 flex items-center gap-2 text-xs text-muted"><CalendarClock size={15} /> Próximo cupo: {disponibilidad.proxima_fecha_disponible}</p>
        ) : null}
      </div>
      <Link aria-disabled={!disponibilidad.puede_solicitar} to={disponibilidad.puede_solicitar ? `/app/solicitudes/nueva?modalidad=${conSalario ? 'con_salario' : 'sin_salario'}` : '#'}>
        <Button className="mt-6 w-full" disabled={!disponibilidad.puede_solicitar} type="button">Solicitar</Button>
      </Link>
    </article>
  );
}

export function FuncionarioDashboardPage() {
  const query = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const disponibilidad = query.data.data;

  return (
    <div className="mx-auto max-w-5xl space-y-7">
      <PageHeader eyebrow="Autoservicio 24/7" title="Solicitar certificación laboral" description="Seleccione la modalidad. Si no requiere pago, el PDF se genera en el momento." />
      <div className="flex items-center gap-3 border-y border-border py-4 text-sm text-muted">
        <ShieldCheck className="shrink-0 text-villavoGreen" size={20} />
        Un cupo CON salario y uno SIN salario por mes calendario. Sus datos se toman de las fuentes institucionales.
      </div>
      <div className="grid gap-5 md:grid-cols-2">
        <Opcion conSalario={false} disponibilidad={disponibilidad.sin_salario} />
        <Opcion conSalario disponibilidad={disponibilidad.con_salario} />
      </div>
      <p className="text-xs text-muted">Este portal no muestra expediente, historial laboral, actuaciones ni información salarial interna.</p>
    </div>
  );
}
