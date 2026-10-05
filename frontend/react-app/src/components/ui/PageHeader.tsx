import type { ReactNode } from 'react';
import { cn } from '../../lib/utils/cn';

type PageHeaderProps = {
  title: string;
  description?: string;
  eyebrow?: string;
  actions?: ReactNode;
  breadcrumbs?: ReactNode;
  appearance?: 'plain' | 'institutional';
};

export function PageHeader({ title, description, eyebrow, actions, breadcrumbs, appearance = 'plain' }: PageHeaderProps) {
  return (
    <header className={cn('flex min-w-0 flex-col gap-4 border-b border-border/80 sm:flex-row sm:items-start sm:justify-between', appearance === 'institutional' ? 'institutional-page-header' : 'pb-5')}>
      <div className="relative min-w-0">
        {breadcrumbs ? <nav aria-label="Ruta de navegación" className="mb-3 text-sm text-muted">{breadcrumbs}</nav> : null}
        {eyebrow ? <p className="eyebrow mb-1.5">{eyebrow}</p> : null}
        <h1 className="break-words text-2xl font-bold tracking-[-0.025em] text-text sm:text-[1.75rem]">{title}</h1>
        {description ? <p className="mt-2 max-w-3xl text-sm leading-6 text-muted sm:text-base">{description}</p> : null}
      </div>
      {actions ? <div className="relative flex max-w-full flex-wrap gap-2 sm:shrink-0">{actions}</div> : null}
    </header>
  );
}
