import { useQuery } from '@tanstack/react-query';
import { CalendarClock, FileText } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { PageHeader } from '../../../components/ui/PageHeader';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import type { DisponibilidadModalidad } from '../../solicitudes/types/solicitud.types';

function ModalidadCard({
  titulo,
  descripcion,
  modalidad,
  disponibilidad,
}: {
  titulo: string;
  descripcion: string;
  modalidad: 'con_salario' | 'sin_salario';
  disponibilidad: DisponibilidadModalidad;
}) {
  return (
    <Card className="flex h-full flex-col" title={titulo} description={descripcion}>
      <div className="mt-auto space-y-4">
        <p className={disponibilidad.puede_solicitar ? 'text-sm font-semibold text-villavoGreen' : 'text-sm font-semibold text-villavoRed'}>
          {disponibilidad.puede_solicitar ? 'Disponible' : 'No disponible este mes'}
        </p>
        {!disponibilidad.puede_solicitar && disponibilidad.proxima_fecha_disponible ? (
          <p className="flex items-center gap-2 text-sm text-muted">
            <CalendarClock size={16} /> Disponible nuevamente el {disponibilidad.proxima_fecha_disponible}.
          </p>
        ) : null}
        <Link to={`/app/solicitudes/nueva?modalidad=${modalidad}`}>
          <Button className="w-full" disabled={!disponibilidad.puede_solicitar} type="button">
            Solicitar
          </Button>
        </Link>
      </div>
    </Card>
  );
}

export function FuncionarioDashboardPage() {
  const disponibilidadQuery = useQuery({
    queryKey: ['disponibilidad-certificacion'],
    queryFn: solicitudesService.disponibilidad,
  });

  if (disponibilidadQuery.isLoading) return <LoadingState />;
  if (disponibilidadQuery.isError || !disponibilidadQuery.data) return <ErrorState />;

  const disponibilidad = disponibilidadQuery.data.data;

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Certificaciones laborales"
        title="Solicitar certificación laboral"
        description="Seleccione la modalidad que necesita. El sistema confirmará el número de radicado al recibirla."
      />
      <div className="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-govBlue">
        Puede solicitar máximo una certificación con salario y una sin salario por mes.
      </div>
      <div className="grid gap-6 md:grid-cols-2">
        <ModalidadCard
          titulo="Certificación laboral SIN salario"
          descripcion="Certifica la vinculación laboral sin incluir valores salariales."
          modalidad="sin_salario"
          disponibilidad={disponibilidad.sin_salario}
        />
        <ModalidadCard
          titulo="Certificación laboral CON salario"
          descripcion="Incluye el salario obtenido de la fuente institucional registrada."
          modalidad="con_salario"
          disponibilidad={disponibilidad.con_salario}
        />
      </div>
      <p className="flex items-center gap-2 text-xs text-muted">
        <FileText size={15} /> El portal no expone su expediente, historial laboral ni información salarial interna.
      </p>
    </div>
  );
}
