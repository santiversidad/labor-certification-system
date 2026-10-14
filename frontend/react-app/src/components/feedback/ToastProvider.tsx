/* eslint-disable react-refresh/only-export-components -- provider and its consuming hook share one private context */
import { CheckCircle2, Info, TriangleAlert, X, XCircle } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

type ToastTone = 'success' | 'error' | 'warning' | 'info';
type Toast = { id: number; message: string; tone: ToastTone };
type ToastContextValue = { showToast: (message: string, tone?: ToastTone) => void };

const ToastContext = createContext<ToastContextValue | null>(null);
const icons = { success: CheckCircle2, error: XCircle, warning: TriangleAlert, info: Info };
const toneClasses = {
  success: 'border-success/25 text-success',
  error: 'border-error/25 text-error',
  warning: 'border-warning/25 text-warning',
  info: 'border-info/25 text-info',
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const removeToast = useCallback((id: number) => setToasts((items) => items.filter((toast) => toast.id !== id)), []);
  const showToast = useCallback((message: string, tone: ToastTone = 'info') => {
    const id = Date.now();
    setToasts((items) => [...items, { id, message, tone }]);
    window.setTimeout(() => removeToast(id), 4500);
  }, [removeToast]);
  const value = useMemo(() => ({ showToast }), [showToast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div aria-live="polite" className="fixed bottom-4 right-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" role="status">
        {toasts.map((toast) => {
          const Icon = icons[toast.tone];
          return (
            <div className={`flex animate-fade-up items-start gap-3 rounded-lg border bg-surface p-4 text-sm shadow-raised ${toneClasses[toast.tone]}`} key={toast.id}>
              <Icon className="mt-0.5 shrink-0" size={18} />
              <p className="flex-1 font-medium text-text">{toast.message}</p>
              <button aria-label="Cerrar notificación" className="rounded p-0.5 text-muted hover:bg-surface-muted hover:text-text" onClick={() => removeToast(toast.id)} type="button"><X size={16} /></button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}
export function useToast() {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useToast debe usarse dentro de ToastProvider.');
  return context;
}
