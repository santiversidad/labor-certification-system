import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { PagoValidationModal } from '../components/PagoValidationModal';
import { PagosTable } from '../components/PagosTable';
import { pagosService } from '../services/pagos.service';

export function PagosPage() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { data, isLoading, isError } = useQuery({
    queryKey: ['pagos'],
    queryFn: pagosService.list,
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
        <h1 className="text-2xl font-semibold text-text">Pagos</h1>
        <p className="mt-1 text-sm text-muted">Revision de soportes de pago asociados a solicitudes.</p>
      </div>
      <Card actions={<Button onClick={() => setIsModalOpen(true)} type="button" variant="secondary">Validar pago</Button>} title="Soportes de pago" description="Tabla mock lista para integracion.">
        <PagosTable pagos={data.data.data} />
      </Card>
      <PagoValidationModal onClose={() => setIsModalOpen(false)} open={isModalOpen} />
    </div>
  );
}
