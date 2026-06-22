import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { EmptyState } from '../../../components/ui/EmptyState';
import { PageHeader } from '../../../components/ui/PageHeader';
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

  const certificados = data.data ?? [];

  return (
    <div className="space-y-6">
      <PageHeader title="Certificados" description="Consulte certificados generados, estado y solicitud asociada." />
      <Card title="Certificados disponibles">
        {certificados.length ? <CertificadosTable certificados={certificados} /> : <EmptyState title="Sin certificados" description="No hay certificados generados para consultar." />}
      </Card>
    </div>
  );
}
