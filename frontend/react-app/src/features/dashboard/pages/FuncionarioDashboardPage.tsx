import { useQuery } from '@tanstack/react-query';
import { ArrowRight, CalendarCheck, CalendarClock, Check, FileText, ListChecks } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Skeleton } from '../../../components/ui/Skeleton';
import { Button } from '../../../components/ui/Button';
import { SpotlightCard } from '../../../components/react-bits/SpotlightCard/SpotlightCard';
import { cn } from '../../../lib/utils/cn';
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
  const deep = tipo === 'funciones';
  return (
    <SpotlightCard
      disabled={!available}
      className={cn(
        'self-service-card flex flex-col overflow-hidden rounded-xl border bg-surface transition-[border-color,box-shadow,transform] duration-200',
        available ? 'border-border shadow-soft hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-institutional focus-within:border-primary/40' : 'border-border/90',
      )}
    >
      <div className={cn('self-service-card__top relative border-b border-border/70 px-5 pb-5 pt-6 sm:px-7', deep ? 'self-service-card__top--deep' : '', !available && 'self-service-card__top--off')}>
        <div className="flex items-start justify-between gap-4">
          <span
            className={cn(
              'grid size-14 shrink-0 place-items-center rounded-2xl',
              available ? 'text-white' : 'border border-border bg-surface-muted text-muted',
              available && (deep ? 'bg-gradient-to-br from-primary-hover to-brand-deep shadow-[0_10px_22px_rgb(var(--color-brand-deep)/0.3)]' : 'bg-gradient-to-br from-primary to-primary-hover shadow-[0_10px_22px_rgb(var(--color-primary)/0.28)]'),
            )}
          >
            <Icon aria-hidden="true" size={26} />
          </span>
          <span
            className={cn(
              'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold sm:text-[13px]',
              available ? 'border-success/20 bg-success/[0.09] text-success' : 'border-border bg-surface-muted text-muted',
            )}
          >
            {available ? <span aria-hidden="true" className="size-1.5 rounded-full bg-success" /> : <CalendarClock aria-hidden="true" size={14} />}
            {available ? 'Disponible ahora' : 'No disponible'}
          </span>
        </div>
        <h2 className={cn('mt-5 text-xl font-bold tracking-tight sm:text-[1.375rem]', available ? 'text-text' : 'text-text/80')}>{CERTIFICATE_TYPE_LABELS[tipo]}</h2>
        <p className="mt-2 text-sm leading-6 text-muted sm:text-[15px]">{CERTIFICATE_TYPE_DESCRIPTIONS[tipo]}</p>
      </div>
      <div className="flex flex-1 flex-col gap-5 px-5 pb-6 pt-5 sm:px-7 sm:pb-7">
        <p
          className={cn(
            'flex items-center gap-3 rounded-md border border-dashed px-4 py-3 text-sm font-medium leading-5',
            available ? 'border-success/30 bg-success/[0.06] text-text' : 'border-border bg-surface-muted/55 text-muted',
          )}
        >
          <span className={cn('grid size-7 shrink-0 place-items-center rounded-full bg-surface', available ? 'text-success shadow-sm' : 'border border-border text-muted')}>
            <Check aria-hidden="true" size={15} strokeWidth={2.6} />
          </span>
          {note}
        </p>
        {!available ? (
          <div className="flex items-center gap-3.5 rounded-md border border-warning/25 border-l-4 border-l-warning bg-amber-50 px-4 py-3.5 text-text">
            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-surface text-warning shadow-sm"><CalendarClock aria-hidden="true" size={21} /></span>
            <div>
              <p className="text-sm font-semibold text-text/85">Próxima disponibilidad</p>
              <p className="mt-0.5 text-lg font-bold tracking-tight">{availability.proxima_fecha_disponible ? formatLongDate(availability.proxima_fecha_disponible) : 'Fecha por confirmar'}</p>
            </div>
          </div>
        ) : null}
        <Button
          className={cn(
            'mt-auto min-h-[3.25rem] w-full gap-2.5 text-base',
            available ? 'shadow-[0_6px_16px_rgb(var(--color-primary)/0.25)]' : 'disabled:border disabled:border-border disabled:bg-surface-muted disabled:text-muted disabled:opacity-100 disabled:shadow-none',
          )}
          disabled={!available}
          onClick={() => navigate(`/app/solicitudes/nueva/${tipo}`)}
          type="button"
        >
          Solicitar {tipo === 'sencillo' ? 'sencillo' : 'con funciones'}
          <span aria-hidden="true" className={cn('grid size-7 place-items-center rounded-full', available ? 'bg-white/20' : 'bg-border/60')}><ArrowRight size={16} strokeWidth={2.4} /></span>
        </Button>
      </div>
    </SpotlightCard>
  );
}

export function FuncionarioDashboardPage() {
  const query = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });

  return (
    <div className="app-page max-w-6xl">
      <section className="self-service-hero overflow-hidden rounded-xl shadow-institutional">
        <div className="max-w-2xl px-5 pb-6 pt-7 sm:px-10 sm:pt-9">
          <p className="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/[0.08] px-3 py-1.5 text-xs font-bold uppercase tracking-[0.13em] text-primary">
            <span aria-hidden="true" className="size-1.5 rounded-full bg-success shadow-[0_0_0_4px_rgb(var(--color-success)/0.15)]" />
            Autoservicio 24/7
          </p>
          <h1 className="mt-3.5 break-words text-[1.75rem] font-bold leading-tight tracking-[-0.025em] text-text sm:text-[2.125rem]">Certificaciones laborales</h1>
          <p className="mt-2.5 text-sm leading-6 text-muted sm:text-base sm:leading-7">Seleccione el contenido que necesita. El sistema validará sus datos y expedirá el documento automáticamente cuando corresponda.</p>
        </div>
        <div className="mx-5 mb-6 flex max-w-3xl items-start gap-4 rounded-lg border border-info/20 border-l-4 border-l-info bg-surface/90 px-4 py-4 shadow-soft backdrop-blur-sm sm:mx-10 sm:mb-8 sm:px-5">
          <span className="grid size-10 shrink-0 place-items-center rounded-full bg-info/10 text-info"><CalendarCheck aria-hidden="true" size={20} /></span>
          <div className="min-w-0">
            <p className="font-bold leading-5 text-text">Disponibilidad mensual de certificados</p>
            <p className="mt-1 text-sm leading-6 text-muted">Puede generar un certificado sencillo y uno con funciones cada mes. Si ya solicitó alguno, le indicaremos cuándo volverá a estar disponible.</p>
          </div>
        </div>
      </section>
      {query.isError ? <ErrorState message="No fue posible consultar la disponibilidad de certificaciones." /> : null}
      {query.isLoading ? <div className="grid gap-6 md:grid-cols-2" aria-label="Cargando tipos de certificado"><Skeleton className="h-80" /><Skeleton className="h-80" /></div> : null}
      {query.data ? (
        <div className="grid gap-6 md:grid-cols-2">
          <CertificationOption availability={query.data.data.sencillo} icon={FileText} note="Se valida su vinculación laboral vigente." tipo="sencillo" />
          <CertificationOption availability={query.data.data.funciones} icon={ListChecks} note="Se valida la ficha asignada antes de generar." tipo="funciones" />
        </div>
      ) : null}
    </div>
  );
}
