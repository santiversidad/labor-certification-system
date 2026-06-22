import { Button } from '../../../components/ui/Button';
import { Modal } from '../../../components/ui/Modal';

type PagoValidationModalProps = {
  open: boolean;
  onClose: () => void;
};

export function PagoValidationModal({ open, onClose }: PagoValidationModalProps) {
  return (
    <Modal onClose={onClose} open={open} title="Validar soporte de pago">
      <p className="text-sm text-muted">Modal placeholder listo para conectar con el endpoint de validacion de pagos.</p>
      <div className="mt-4 flex justify-end gap-2">
        <Button onClick={onClose} type="button" variant="secondary">Cancelar</Button>
        <Button onClick={onClose} type="button">Aprobar</Button>
      </div>
    </Modal>
  );
}
