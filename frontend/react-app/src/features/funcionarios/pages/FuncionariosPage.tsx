import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { FuncionariosTable } from '../components/FuncionariosTable';
import { funcionariosService } from '../services/funcionarios.service';

export function FuncionariosPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['funcionarios'],
    queryFn: funcionariosService.list,
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
        <h1 className="text-2xl font-semibold text-text">Funcionarios</h1>
        <p className="mt-1 text-sm text-muted">Administracion de funcionarios vinculados a certificaciones laborales.</p>
      </div>
      <Card title="Funcionarios registrados" description="Personal vinculado a la entidad.">
        <FuncionariosTable funcionarios={data.data} />
      </Card>
    </div>
  );
}
