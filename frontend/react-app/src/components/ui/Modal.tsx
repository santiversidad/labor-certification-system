import type { ReactNode } from 'react';
import { X } from 'lucide-react';
import { Button } from './Button';

type ModalProps = {
  open: boolean;
  title: string;
  children: ReactNode;
  onClose: () => void;
};

export function Modal({ open, title, children, onClose }: ModalProps) {
  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg rounded-lg bg-surface p-5 shadow-xl">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-text">{title}</h2>
          <Button aria-label="Cerrar modal" icon={<X size={18} />} onClick={onClose} type="button" variant="ghost" />
        </div>
        {children}
      </div>
    </div>
  );
}
