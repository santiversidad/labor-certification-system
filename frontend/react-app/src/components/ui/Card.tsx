import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../../lib/utils/cn';

type CardProps = HTMLAttributes<HTMLDivElement> & {
  title?: string;
  description?: string;
  actions?: ReactNode;
};

export function Card({ className, title, description, actions, children, ...props }: CardProps) {
  return (
    <section className={cn('rounded-lg border border-border bg-surface p-5 shadow-soft sm:p-6', className)} {...props}>
      {(title || description || actions) && (
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            {title ? <h2 className="text-base font-bold tracking-tight text-text">{title}</h2> : null}
            {description ? <p className="mt-1 max-w-3xl text-sm leading-6 text-muted">{description}</p> : null}
          </div>
          {actions}
        </div>
      )}
      {children}
    </section>
  );
}
