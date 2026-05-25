import type { LucideIcon } from 'lucide-react';
import { ArrowRight } from 'lucide-react';
import { Link } from 'react-router-dom';

export function AdminModuleCard({
  label,
  description,
  path,
  icon: Icon,
}: {
  label: string;
  description: string;
  path: string;
  icon: LucideIcon;
}) {
  return (
    <Link className="group rounded-md border border-border bg-surface p-4 transition hover:border-govBlue hover:bg-blue-50" to={path}>
      <div className="flex items-start justify-between gap-3">
        <span className="rounded-md bg-background p-2 text-govBlue group-hover:bg-white">
          <Icon size={20} />
        </span>
        <ArrowRight className="text-muted group-hover:text-govBlue" size={18} />
      </div>
      <p className="mt-4 text-sm font-semibold text-text">{label}</p>
      <p className="mt-1 text-xs leading-5 text-muted">{description}</p>
    </Link>
  );
}
