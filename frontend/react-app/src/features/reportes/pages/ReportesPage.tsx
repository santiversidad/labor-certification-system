import { useQuery } from '@tanstack/react-query';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Alert } from '../../../components/ui/Alert';
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
    <div className="app-page">
      <PageHeader eyebrow="Información institucional" title="Reportes" description="Resumen acumulado respaldado por el servicio actual de reportes." />
      <ReportsSummary resumen={data.data} />
      <Alert title="Alcance de los datos" tone="info">El endpoint actual entrega cifras acumuladas y no admite rangos de fecha. No se presenta un filtro que pueda inducir a interpretar un periodo inexistente.</Alert>
    </div>
  );
}
