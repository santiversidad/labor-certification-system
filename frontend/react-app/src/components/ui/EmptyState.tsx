import type { ReactNode } from 'react';
import { FileSearch } from 'lucide-react';

type EmptyStateProps = {
  title: string;
  description: string;
  action?: ReactNode;
};

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="rounded-lg border border-dashed border-border bg-surface p-8 text-center">
      <FileSearch className="mx-auto mb-3 text-muted" size={32} />
      <h3 className="text-base font-semibold text-text">{title}</h3>
      <p className="mx-auto mt-2 max-w-md text-sm text-muted">{description}</p>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}
