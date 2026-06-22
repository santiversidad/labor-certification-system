import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { CertificadosTable } from '../components/CertificadosTable';
import { certificadosService } from '../services/certificados.service';

export function CertificadosPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['certificados'],
    queryFn: certificadosService.list,
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
        <h1 className="text-2xl font-semibold text-text">Certificados</h1>
        <p className="mt-1 text-sm text-muted">Consulta, descarga y seguimiento de certificados generados.</p>
      </div>
      <Card title="Certificados disponibles" description="Listado de certificados generados.">
        <CertificadosTable certificados={data.data} />
      </Card>
    </div>
  );
}
