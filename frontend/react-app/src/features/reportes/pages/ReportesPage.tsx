import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
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
      <div>
        <h1 className="text-2xl font-semibold text-text">Reportes</h1>
        <p className="mt-1 text-sm text-muted">Resumen institucional inicial para analitica y exportaciones futuras.</p>
      </div>
      <ReportsSummary resumen={data.data} />
      <Card title="Integración pendiente" description="El endpoint /reportes aún no está disponible en el backend. Pantalla preparada para conectar filtros, gráficas y exportaciones.">
        <p className="text-sm text-muted">Esta vista quedará operativa al implementar el módulo de reportes en el backend.</p>
      </Card>
    </div>
  );
}
