import { Loader2 } from 'lucide-react';

export function LoadingState({ label = 'Cargando informacion...' }: { label?: string }) {
  return (
    <div className="flex min-h-32 items-center justify-center gap-3 rounded-lg border border-border bg-surface px-4 py-6 text-sm font-medium text-muted" aria-live="polite" role="status">
      <Loader2 className="animate-spin text-primary" size={20} />
      <span>{label}</span>
    </div>
  );
}
