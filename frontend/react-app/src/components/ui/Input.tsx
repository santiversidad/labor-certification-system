import type { InputHTMLAttributes } from 'react';
import { cn } from '../../lib/utils/cn';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
};

export function Input({ className, label, error, id, ...props }: InputProps) {
  const inputId = id ?? props.name;

  return (
    <label className="block space-y-1 text-sm">
      {label ? <span className="font-medium text-text">{label}</span> : null}
      <input
        id={inputId}
        className={cn(
          'min-h-10 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none transition placeholder:text-muted focus:border-govBlue focus:ring-2 focus:ring-govBlue/20',
          error && 'border-villavoRed focus:border-villavoRed focus:ring-villavoRed/20',
          className,
        )}
        {...props}
      />
      {error ? <span className="text-xs text-villavoRed">{error}</span> : null}
    </label>
  );
}
