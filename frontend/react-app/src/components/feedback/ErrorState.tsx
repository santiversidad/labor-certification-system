import { AlertCircle } from 'lucide-react';

export function ErrorState({ message = 'No fue posible cargar la informacion.' }: { message?: string }) {
  return (
    <div className="flex items-start gap-3 rounded-md border border-error/20 bg-error/5 px-4 py-3 text-sm text-error" role="alert">
      <AlertCircle className="mt-0.5 shrink-0" size={18} />
      <span>{message}</span>
    </div>
  );
}
