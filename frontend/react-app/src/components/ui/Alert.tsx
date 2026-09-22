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

export function Alert({ title, children, tone = 'info', className }: { title?: string; children: ReactNode; tone?: Tone; className?: string }) {
  const Icon = icons[tone];
  return (
    <div className={cn('flex items-start gap-3 rounded-md border p-4 text-sm', styles[tone], className)} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon className="mt-0.5 shrink-0" size={19} />
      <div className="min-w-0 text-text">{title ? <p className="font-bold">{title}</p> : null}<div className={title ? 'mt-1 text-muted' : ''}>{children}</div></div>
    </div>
  );
}

