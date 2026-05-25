import type { HTMLAttributes } from 'react';
import { cn } from '../../lib/utils/cn';

type BadgeTone = 'blue' | 'green' | 'red' | 'gold' | 'gray';

type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
  tone?: BadgeTone;
};

const toneClasses: Record<BadgeTone, string> = {
  blue: 'bg-blue-50 text-govBlue',
  green: 'bg-green-50 text-villavoGreen',
  red: 'bg-red-50 text-villavoRed',
  gold: 'bg-yellow-50 text-yellow-800',
  gray: 'bg-gray-100 text-muted',
};

export function Badge({ className, tone = 'gray', children, ...props }: BadgeProps) {
  return (
    <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', toneClasses[tone], className)} {...props}>
      {children}
    </span>
  );
}
