import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { AuditoriaTable } from '../components/AuditoriaTable';
import { auditoriaService } from '../services/auditoria.service';

export function AuditoriaPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['auditoria'],
    queryFn: auditoriaService.list,
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
        <h1 className="text-2xl font-semibold text-text">Auditoria</h1>
        <p className="mt-1 text-sm text-muted">Registro inicial de acciones del sistema.</p>
      </div>
      <Card title="Eventos recientes" description="Modulo preparado para trazabilidad de operaciones Laravel.">
        <AuditoriaTable logs={data.data.data} />
      </Card>
    </div>
  );
}
