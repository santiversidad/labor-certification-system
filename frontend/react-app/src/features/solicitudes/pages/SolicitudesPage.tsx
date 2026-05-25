import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { SolicitudesTable } from '../components/SolicitudesTable';
import { solicitudesService } from '../services/solicitudes.service';

export function SolicitudesPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['solicitudes'],
    queryFn: solicitudesService.list,
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
        <h1 className="text-2xl font-semibold text-text">Solicitudes</h1>
        <p className="mt-1 text-sm text-muted">Listado inicial de solicitudes con datos mock.</p>
      </div>
      <Card title="Solicitudes registradas" description="La tabla consume servicios mock y quedara lista para la API.">
        <SolicitudesTable solicitudes={data.data.data} />
      </Card>
    </div>
  );
}
