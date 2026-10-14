import { CheckCircle2, Info, TriangleAlert, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '../../lib/utils/cn';

type Tone = 'success' | 'error' | 'warning' | 'info';
const icons = { success: CheckCircle2, error: XCircle, warning: TriangleAlert, info: Info };
const styles = {
  success: 'border-success/20 bg-success/5 text-success',
  error: 'border-error/20 bg-error/5 text-error',
  warning: 'border-warning/25 bg-amber-50 text-warning',
  info: 'border-info/20 bg-info/5 text-info',
};

export function Alert({ title, children, variant, tone, announce = false, className }: { title?: string; children: ReactNode; variant?: Tone; tone?: Tone; announce?: boolean; className?: string }) {
  const appearance = variant ?? tone ?? 'info';
  const Icon = icons[appearance];
  return (
    <div className={cn('flex items-start gap-3 rounded-md border p-4 text-sm leading-6', styles[appearance], className)} role={appearance === 'error' ? 'alert' : announce ? 'status' : undefined}>
      <Icon aria-hidden="true" className="mt-0.5 shrink-0" size={19} />
      <div className="min-w-0 text-text">{title ? <p className="font-bold leading-5">{title}</p> : null}<div className={title ? 'mt-1 text-text/85' : ''}>{children}</div></div>
    </div>
  );
}
