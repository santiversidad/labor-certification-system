import { useId, type TextareaHTMLAttributes } from 'react';
import { cn } from '../../lib/utils/cn';

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: string; hint?: string; error?: string };

export function Textarea({ className, label, hint, error, id, ...props }: TextareaProps) {
  const generatedId = useId();
  const fieldId = id ?? props.name ?? generatedId;
  return (
    <div className="block space-y-1.5 text-sm">
      {label ? <label className="block font-semibold text-text" htmlFor={fieldId}>{label}</label> : null}
      <textarea aria-describedby={error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined} aria-invalid={Boolean(error)} className={cn('field-control min-h-28 resize-y', error && 'border-error', className)} id={fieldId} {...props} />
      {hint && !error ? <span className="block text-xs text-muted" id={`${fieldId}-hint`}>{hint}</span> : null}
      {error ? <span className="block text-xs font-medium text-error" id={`${fieldId}-error`} role="alert">{error}</span> : null}
    </div>
  );
}
