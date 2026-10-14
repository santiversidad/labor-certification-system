import type { ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

type ConfirmDialogProps = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel?: string;
  confirmVariant?: 'primary' | 'danger';
  disabled?: boolean;
  children?: ReactNode;
  onCancel: () => void;
  onConfirm: () => void;
};

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  cancelLabel = 'Cancelar',
  confirmVariant = 'primary',
  disabled = false,
  children,
  onCancel,
  onConfirm,
}: ConfirmDialogProps) {
  return (
    <Modal onClose={onCancel} open={open} title={title}>
      <p className="text-sm text-muted">{description}</p>
      {children ? <div className="mt-4">{children}</div> : null}
      <div className="mt-5 flex justify-end gap-2">
        <Button onClick={onCancel} type="button" variant="secondary">
          {cancelLabel}
        </Button>
        <Button disabled={disabled} onClick={onConfirm} type="button" variant={confirmVariant}>
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  );
}
