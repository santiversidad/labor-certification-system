import { useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { Modal } from '../../../components/ui/Modal';
import type { PagoSoporte } from '../types/pago.types';

type PagoValidationModalProps = {
  open: boolean;
  mode: 'aprobar' | 'rechazar';
  pago: PagoSoporte | null;
  loading?: boolean;
  onClose: () => void;
  onSubmit: (observacion?: string) => void;
};

export function PagoValidationModal({ open, mode, pago, loading = false, onClose, onSubmit }: PagoValidationModalProps) {
  const [observacion, setObservacion] = useState('');
  const [error, setError] = useState('');

  function handleSubmit() {
    if (mode === 'rechazar' && !observacion.trim()) {
      setError('La observacion es obligatoria para rechazar el pago.');
      return;
    }

    onSubmit(observacion);
  }

  return (
    <Modal onClose={onClose} open={open} title={mode === 'aprobar' ? 'Aprobar soporte de pago' : 'Rechazar soporte de pago'}>
      <div className="space-y-4 text-sm">
        <p className="text-muted">Solicitud: <span className="font-medium text-text">{pago?.solicitudId ?? 'No seleccionada'}</span></p>
        {mode === 'rechazar' ? (
          <label className="block space-y-1">
            <span className="font-medium text-text">Observacion obligatoria</span>
            <textarea className="min-h-28 w-full rounded-md border border-border bg-surface px-3 py-2" value={observacion} onChange={(event) => setObservacion(event.target.value)} />
            {error ? <span className="text-xs text-villavoRed">{error}</span> : null}
          </label>
        ) : (
          <p className="text-muted">Confirme que el soporte fue revisado y corresponde a la solicitud.</p>
        )}
      </div>
      <div className="mt-5 flex justify-end gap-2">
        <Button disabled={loading} onClick={onClose} type="button" variant="secondary">Cancelar</Button>
        <Button disabled={loading} onClick={handleSubmit} type="button" variant={mode === 'rechazar' ? 'danger' : 'primary'}>
          {loading ? 'Procesando...' : mode === 'aprobar' ? 'Aprobar pago' : 'Rechazar pago'}
        </Button>
      </div>
    </Modal>
  );
}
