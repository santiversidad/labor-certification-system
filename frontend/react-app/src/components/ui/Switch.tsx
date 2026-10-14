import { cn } from '../../lib/utils/cn';

type SwitchProps = { checked: boolean; onChange: (checked: boolean) => void; label: string; description?: string; disabled?: boolean };

export function Switch({ checked, onChange, label, description, disabled }: SwitchProps) {
  return (
    <label className="flex cursor-pointer items-start justify-between gap-6">
      <span><span className="block font-bold text-text">{label}</span>{description ? <span className="mt-1 block text-sm leading-6 text-muted">{description}</span> : null}</span>
      <button aria-checked={checked} aria-label={label} className={cn('relative mt-0.5 h-7 w-12 shrink-0 rounded-full border transition-colors', checked ? 'border-primary bg-primary' : 'border-border bg-surface-muted', disabled && 'cursor-not-allowed opacity-50')} disabled={disabled} onClick={() => onChange(!checked)} role="switch" type="button">
        <span className={cn('absolute top-1 h-[18px] w-[18px] rounded-full bg-white shadow-sm transition-transform', checked ? 'translate-x-[22px]' : 'translate-x-1')} />
      </button>
    </label>
  );
}

