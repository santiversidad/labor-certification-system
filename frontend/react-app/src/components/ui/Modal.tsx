import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { Button } from './Button';

type ModalProps = {
  open: boolean;
  title: string;
  children: ReactNode;
  onClose: () => void;
};

export function Modal({ open, title, children, onClose }: ModalProps) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    panelRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      previous?.focus();
    };
  }, [onClose, open]);

  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[2px]" onMouseDown={(event) => { if (event.currentTarget === event.target) onClose(); }}>
      <div aria-labelledby={titleId} aria-modal="true" className="w-full max-w-lg animate-fade-up rounded-lg bg-surface p-5 shadow-raised sm:p-6" ref={panelRef} role="dialog" tabIndex={-1}>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold text-text" id={titleId}>{title}</h2>
          <Button aria-label="Cerrar modal" icon={<X size={18} />} onClick={onClose} type="button" variant="ghost" />
        </div>
        {children}
      </div>
    </div>
  );
}
