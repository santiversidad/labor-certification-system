import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { ReportsSummary } from '../components/ReportsSummary';
import { reportesService } from '../services/reportes.service';

export function ReportesPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['reportes-summary'],
    queryFn: reportesService.getSummary,
  });

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Reportes" description="Resumen institucional inicial para analitica y exportaciones futuras." />
      <ReportsSummary resumen={data.data} />
      <Card title="Integracion pendiente" description="La pantalla esta lista para conectar filtros, graficas y exportaciones desde la API Laravel.">
        <p className="text-sm text-muted">Los indicadores consumen el servicio de reportes y quedan listos para un endpoint futuro mas detallado.</p>
      </Card>
    </div>
  );
}
