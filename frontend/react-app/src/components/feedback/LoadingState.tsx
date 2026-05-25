import { Loader2 } from 'lucide-react';

export function LoadingState({ label = 'Cargando informacion...' }: { label?: string }) {
  return (
    <div className="flex items-center gap-2 rounded-md border border-border bg-surface px-4 py-3 text-sm text-muted">
      <Loader2 className="animate-spin text-govBlue" size={18} />
      {label}
    </div>
  );
}
