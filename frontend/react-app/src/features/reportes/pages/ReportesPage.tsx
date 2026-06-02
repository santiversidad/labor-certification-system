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
      <Card title="Integracion pendiente" description="La pantalla esta lista para conectar filtros, graficas y exportaciones desde la API Laravel.">
        <p className="text-sm text-muted">Los indicadores actuales provienen de servicios mock centralizados.</p>
      </Card>
    </div>
  );
}
