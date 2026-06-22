import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { PageHeader } from '../../../components/ui/PageHeader';
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
      <PageHeader title="Detalle de certificado" description="Consulte metadatos y descargue solo si el certificado esta vigente." />
      <Card
        actions={(
          <div className="flex gap-2">
            <DownloadCertificateButton disabled={!canDownload} url={certificado.descargaUrl} />
            <Button disabled title="pendiente de endpoint" type="button" variant="danger">Anular</Button>
          </div>
        )}
        title={certificado.codigoValidacion}
        description={!canDownload ? 'La descarga solo esta disponible para certificados vigentes.' : 'Certificado vigente disponible para descarga.'}
      >
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Solicitud</dt><dd className="font-medium">{certificado.solicitudRadicado ?? certificado.solicitudId}</dd></div>
          <div><dt className="text-muted">Funcionario</dt><dd className="font-medium">{certificado.funcionarioNombre ?? 'No registrado'}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><Badge tone={certificado.estado === 'vigente' || certificado.estado === 'descargado' ? 'green' : 'red'}>{certificado.estado}</Badge></dd></div>
          <div><dt className="text-muted">Generacion</dt><dd className="font-medium">{certificado.fechaGeneracion}</dd></div>
          <div><dt className="text-muted">Vencimiento</dt><dd className="font-medium">{certificado.fechaVencimiento}</dd></div>
        </dl>
        <p className="mt-4 text-xs text-muted">Anulacion pendiente de endpoint.</p>
      </Card>
    </div>
  );
}
