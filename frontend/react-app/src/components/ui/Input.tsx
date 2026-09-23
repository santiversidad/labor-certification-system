import { useId, type InputHTMLAttributes } from 'react';
import { cn } from '../../lib/utils/cn';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
};

export function Input({ className, label, error, id, ...props }: InputProps) {
  const generatedId = useId();
  const inputId = id ?? props.name ?? generatedId;
  const errorId = error ? `${inputId}-error` : undefined;

  return (
    <div className="block space-y-1.5 text-sm">
      {label ? <label className="block font-semibold text-text" htmlFor={inputId}>{label}</label> : null}
      <input
        aria-describedby={errorId}
        aria-invalid={Boolean(error)}
        id={inputId}
        className={cn(
          'field-control',
          error && 'border-error focus:border-error focus:ring-error/15',
          className,
        )}
        {...props}
      />
      {error ? <span className="block text-xs font-medium text-error" id={errorId} role="alert">{error}</span> : null}
    </div>
  );
}
