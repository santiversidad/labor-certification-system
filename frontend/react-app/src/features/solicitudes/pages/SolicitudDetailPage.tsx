import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';

const tipoLabels: Record<string, string> = {
  laboral: 'Certificación laboral',
  funciones: 'Certificación de funciones',
  salario: 'Certificación de salario',
  laboral_salario: 'Certificación laboral con salario',
};

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

  const solicitud = data.data;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-text">Detalle de solicitud</h1>
        <p className="mt-1 text-sm text-muted">Información completa de la solicitud y su estado actual.</p>
      </div>
      <Card title={`Solicitud N° ${solicitud.id}`} description="Resumen actual de la solicitud.">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Tipo</dt><dd className="font-medium">{tipoLabels[solicitud.tipo_certificado] ?? solicitud.tipo_certificado}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><SolicitudStatusBadge estado={solicitud.estado} /></dd></div>
          <div><dt className="text-muted">Fecha</dt><dd className="font-medium">{solicitud.created_at ? formatDate(solicitud.created_at) : '—'}</dd></div>
          <div><dt className="text-muted">Funcionario</dt><dd className="font-medium">{solicitud.funcionario ? `${solicitud.funcionario.nombres} ${solicitud.funcionario.apellidos}` : '—'}</dd></div>
          <div><dt className="text-muted">Requiere pago</dt><dd className="font-medium">{solicitud.requiere_pago ? 'Sí' : 'No'}</dd></div>
          <div><dt className="text-muted">Requiere salario</dt><dd className="font-medium">{solicitud.requiere_salario ? 'Sí' : 'No'}</dd></div>
          <div className="sm:col-span-2"><dt className="text-muted">Observaciones</dt><dd className="font-medium">{solicitud.observaciones ?? 'Sin observaciones'}</dd></div>
          {solicitud.motivo_rechazo ? (
            <div className="sm:col-span-2"><dt className="text-muted">Motivo de rechazo</dt><dd className="font-medium text-villavoRed">{solicitud.motivo_rechazo}</dd></div>
          ) : null}
        </dl>
      </Card>
    </div>
  );
}
