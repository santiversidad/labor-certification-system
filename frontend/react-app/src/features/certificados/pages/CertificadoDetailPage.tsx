import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { DownloadCertificateButton } from '../components/DownloadCertificateButton';
import { certificadosService } from '../services/certificados.service';

export function CertificadoDetailPage() {
  const { id = '' } = useParams();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['certificado', id],
    queryFn: () => certificadosService.getById(id),
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
        <h1 className="text-2xl font-semibold text-text">Detalle de certificado</h1>
        <p className="mt-1 text-sm text-muted">Descarga placeholder y metadatos listos para API.</p>
      </div>
      <Card actions={<DownloadCertificateButton url={data.data.descargaUrl} />} title={data.data.codigoValidacion} description="Informacion del certificado laboral.">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Solicitud</dt><dd className="font-medium">{data.data.solicitudId}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><Badge tone="green">{data.data.estado}</Badge></dd></div>
          <div><dt className="text-muted">Generacion</dt><dd className="font-medium">{data.data.fechaGeneracion}</dd></div>
          <div><dt className="text-muted">Vencimiento</dt><dd className="font-medium">{data.data.fechaVencimiento}</dd></div>
        </dl>
      </Card>
    </div>
  );
}
