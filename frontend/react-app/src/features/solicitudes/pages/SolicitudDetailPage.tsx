import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';

export function SolicitudDetailPage() {
  const { id = '' } = useParams();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['solicitud', id],
    queryFn: () => solicitudesService.getById(id),
    enabled: Boolean(id),
  });

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-text">Detalle de solicitud</h1>
        <p className="mt-1 text-sm text-muted">Consulta preparada para reemplazar mock por backend real.</p>
      </div>
      <Card title={data.data.radicado} description="Resumen actual de la solicitud.">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Tipo</dt><dd className="font-medium">{data.data.tipo}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><SolicitudStatusBadge estado={data.data.estado} /></dd></div>
          <div><dt className="text-muted">Fecha</dt><dd className="font-medium">{data.data.fechaSolicitud}</dd></div>
          <div><dt className="text-muted">Observaciones</dt><dd className="font-medium">{data.data.observaciones ?? 'Sin observaciones'}</dd></div>
        </dl>
      </Card>
    </div>
  );
}
