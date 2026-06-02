import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../lib/utils/cn';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  icon?: ReactNode;
};

const variantClasses: Record<ButtonVariant, string> = {
  primary: 'bg-govBlue text-white hover:bg-govBlueDark',
  secondary: 'border border-border bg-surface text-text hover:bg-background',
  ghost: 'text-govBlue hover:bg-blue-50',
  danger: 'bg-villavoRed text-white hover:bg-red-800',
};

export function Button({ className, variant = 'primary', icon, children, ...props }: ButtonProps) {
  return (
    <button
      className={cn(
        'inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60',
        variantClasses[variant],
        className,
      )}
      {...props}
    >
      {icon}
      {children}
    </button>
  );
}
