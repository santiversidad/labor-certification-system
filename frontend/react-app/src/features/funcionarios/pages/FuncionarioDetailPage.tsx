import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { funcionariosService } from '../services/funcionarios.service';

export function FuncionarioDetailPage() {
  const { id = '' } = useParams();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['funcionario', id],
    queryFn: () => funcionariosService.getById(id),
    enabled: Boolean(id),
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
        <h1 className="text-2xl font-semibold text-text">Detalle de funcionario</h1>
        <p className="mt-1 text-sm text-muted">Ficha placeholder preparada para consumir el endpoint de funcionarios.</p>
      </div>
      <Card title={`${data.data.nombres} ${data.data.apellidos}`} description="Informacion laboral basica.">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Documento</dt><dd className="font-medium">{data.data.numero_documento}</dd></div>
          <div><dt className="text-muted">Correo</dt><dd className="font-medium">{data.data.correo_institucional ?? 'Sin correo'}</dd></div>
          <div><dt className="text-muted">Dependencia</dt><dd className="font-medium">{data.data.dependencia}</dd></div>
          <div><dt className="text-muted">Cargo</dt><dd className="font-medium">{data.data.cargo?.denominacion ?? 'Sin cargo'}</dd></div>
          <div><dt className="text-muted">Estado</dt><dd><Badge tone="green">{data.data.estado}</Badge></dd></div>
        </dl>
      </Card>
    </div>
  );
}
