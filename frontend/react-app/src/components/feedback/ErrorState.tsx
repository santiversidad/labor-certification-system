import { AlertCircle } from 'lucide-react';

export function ErrorState({ message = 'No fue posible cargar la informacion.' }: { message?: string }) {
  return (
    <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-villavoRed">
      <AlertCircle size={18} />
      {message}
    </div>
  );
}
