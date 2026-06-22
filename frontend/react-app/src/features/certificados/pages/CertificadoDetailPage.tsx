import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { formatDate } from '../../../lib/formatters/dates';
import { DownloadCertificateButton } from '../components/DownloadCertificateButton';
import { certificadosService } from '../services/certificados.service';
import { canDownloadCertificado } from '../utils/certificadoRules';

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

  const certificado = data.data;
  const canDownload = canDownloadCertificado(certificado);

  return (
    <div className="space-y-6">
      <PageHeader title="Detalle de certificado" description="Información del certificado generado." />
      <Card
        actions={<DownloadCertificateButton disabled={!canDownload} />}
        title={certificado.codigo_unico}
        description="Información del certificado laboral."
      >
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Código único</dt><dd className="font-medium">{certificado.codigo_unico}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><Badge tone={certificado.estado === 'anulado' ? 'red' : 'green'}>{certificado.estado}</Badge></dd></div>
          <div><dt className="text-muted">Generación</dt><dd className="font-medium">{certificado.fecha_generacion ? formatDate(certificado.fecha_generacion) : '—'}</dd></div>
          <div><dt className="text-muted">Generado por</dt><dd className="font-medium">{certificado.generado_por?.name ?? '—'}</dd></div>
        </dl>
      </Card>
    </div>
  );
}
