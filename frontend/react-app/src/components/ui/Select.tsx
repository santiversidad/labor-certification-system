import { useId, type SelectHTMLAttributes } from 'react';
import { cn } from '../../lib/utils/cn';

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
  hint?: string;
  error?: string;
};

export function Select({ className, label, hint, error, id, children, ...props }: SelectProps) {
  const generatedId = useId();
  const selectId = id ?? props.name ?? generatedId;
  const describedBy = error ? `${selectId}-error` : hint ? `${selectId}-hint` : undefined;
  return (
    <div className="block space-y-1.5 text-sm">
      {label ? <label className="block font-semibold text-text" htmlFor={selectId}>{label}</label> : null}
      <select aria-describedby={describedBy} aria-invalid={Boolean(error)} className={cn('field-control', error && 'border-error', className)} id={selectId} {...props}>{children}</select>
      {hint && !error ? <span className="block text-xs text-muted" id={`${selectId}-hint`}>{hint}</span> : null}
      {error ? <span className="block text-xs font-medium text-error" id={`${selectId}-error`} role="alert">{error}</span> : null}
    </div>
  );
}
