import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { CargoForm } from '../components/CargoForm';
import { CargosTable } from '../components/CargosTable';
import { cargosService } from '../services/cargos.service';

export function CargosPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['cargos'],
    queryFn: cargosService.list,
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
        <h1 className="text-2xl font-semibold text-text">Cargos y grados</h1>
        <p className="mt-1 text-sm text-muted">Catalogo base de cargos institucionales.</p>
      </div>
      <CargoForm />
      <Card title="Cargos registrados" description="Catálogo de cargos institucionales.">
        <CargosTable cargos={data.data} />
      </Card>
    </div>
  );
}
