import { Badge } from './Badge';

type StatusTone = 'blue' | 'green' | 'red' | 'gold' | 'gray';

type StatusBadgeProps<TStatus extends string> = {
  status: TStatus;
  labels: Record<TStatus, string>;
  tones: Partial<Record<TStatus, StatusTone>>;
};

export function StatusBadge<TStatus extends string>({ status, labels, tones }: StatusBadgeProps<TStatus>) {
  return <Badge tone={tones[status] ?? 'gray'}>{labels[status] ?? status}</Badge>;
}
