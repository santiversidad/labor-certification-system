import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Card } from '../../../components/ui/Card';
import { PageHeader } from '../../../components/ui/PageHeader';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';
import { certificateTypeLabel } from '../utils/certificateType';

export function SolicitudDetailPage() {
  const { id = '' } = useParams();
  const { data, isLoading, isError } = useQuery({ queryKey: ['solicitud', id], queryFn: () => solicitudesService.getById(id), enabled: Boolean(id) });
  if (isLoading) return <LoadingState />;
  if (isError || !data) return <ErrorState />;
  const solicitud = data.data;
  return (
    <div className="space-y-6">
      <PageHeader title={`Solicitud ${solicitud.radicado}`} description="Consulta histórica. La expedición vigente es automática y esta vista no ofrece acciones manuales." actions={<SolicitudStatusBadge estado={solicitud.estado} />} />
      <Card title="Datos de la solicitud">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Tipo</dt><dd className="font-medium text-text">{certificateTypeLabel(solicitud.tipo_certificado)}</dd></div>
          <div><dt className="text-muted">Periodo</dt><dd className="font-medium text-text">{solicitud.periodo_mes}</dd></div>
          <div><dt className="text-muted">Fecha</dt><dd className="font-medium text-text">{solicitud.created_at ? formatDate(solicitud.created_at) : '—'}</dd></div>
        </dl>
      </Card>
    </div>
  );
}
