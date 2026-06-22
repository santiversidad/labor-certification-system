import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { RangoSalarialForm } from '../components/RangoSalarialForm';
import { RangosSalarialesTable } from '../components/RangosSalarialesTable';
import { rangosSalarialesService } from '../services/rangosSalariales.service';

export function RangosSalarialesPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['rangos-salariales'],
    queryFn: rangosSalarialesService.list,
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
        <h1 className="text-2xl font-semibold text-text">Rangos salariales</h1>
        <p className="mt-1 text-sm text-muted">Tabla salarial por cargo, grado y vigencia.</p>
      </div>
      <RangoSalarialForm />
      <Card title="Rangos activos" description="Tabla salarial vigente por código + grado + vigencia.">
        <RangosSalarialesTable rangos={data.data} />
      </Card>
    </div>
  );
}
