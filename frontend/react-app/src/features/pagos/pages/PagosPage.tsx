import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { EmptyState } from '../../../components/ui/EmptyState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { PagoValidationModal } from '../components/PagoValidationModal';
import { PagosTable } from '../components/PagosTable';
import { pagosService } from '../services/pagos.service';
import type { PagoSoporte } from '../types/pago.types';

export function PagosPage() {
  const queryClient = useQueryClient();
  const [selectedPago, setSelectedPago] = useState<PagoSoporte | null>(null);
  const [mode, setMode] = useState<'aprobar' | 'rechazar'>('aprobar');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['pagos'],
    queryFn: pagosService.list,
  });
  const approveMutation = useMutation({
    mutationFn: (id: string) => pagosService.approve(id),
    onSuccess: () => {
      setSelectedPago(null);
      void queryClient.invalidateQueries({ queryKey: ['pagos'] });
    },
  });
  const rejectMutation = useMutation({
    mutationFn: ({ id, observacion }: { id: string; observacion: string }) => pagosService.reject(id, observacion),
    onSuccess: () => {
      setSelectedPago(null);
      void queryClient.invalidateQueries({ queryKey: ['pagos'] });
    },
  });

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  function openModal(nextMode: 'aprobar' | 'rechazar', pago: PagoSoporte) {
    setMode(nextMode);
    setSelectedPago(pago);
  }

  function handleSubmit(observacion = '') {
    if (!selectedPago) {
      return;
    }

    if (mode === 'aprobar') {
      approveMutation.mutate(selectedPago.id);
    } else {
      rejectMutation.mutate({ id: selectedPago.id, observacion });
    }
  }

  const pagos = data.data.data;

  return (
    <div className="space-y-6">
      <PageHeader title="Validacion de pagos" description="Revise soportes cargados, apruebe pagos o rechace con observacion obligatoria." />
      <Card title="Soportes de pago" description="Solicitudes con pago pendiente o en revision.">
        {pagos.length ? (
          <PagosTable pagos={pagos} onApprove={(pago) => openModal('aprobar', pago)} onReject={(pago) => openModal('rechazar', pago)} />
        ) : (
          <EmptyState title="Sin pagos por revisar" description="No hay soportes pendientes de validacion." />
        )}
      </Card>
      <PagoValidationModal
        key={`${mode}-${selectedPago?.id ?? 'none'}`}
        loading={approveMutation.isPending || rejectMutation.isPending}
        mode={mode}
        onClose={() => setSelectedPago(null)}
        onSubmit={handleSubmit}
        open={Boolean(selectedPago)}
        pago={selectedPago}
      />
    </div>
  );
}
