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
      <Card title="Integración pendiente" description="El endpoint /reportes aún no está disponible en el backend. Pantalla preparada para conectar filtros, gráficas y exportaciones.">
        <p className="text-sm text-muted">Esta vista quedará operativa al implementar el módulo de reportes en el backend.</p>
      </Card>
    </div>
  );
}
