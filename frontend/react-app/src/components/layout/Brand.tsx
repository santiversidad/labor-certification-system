import { Landmark } from 'lucide-react';
import { Link } from 'react-router-dom';
import { cn } from '../../lib/utils/cn';

export function Brand({ compact = false, inverse = false, to = '/' }: { compact?: boolean; inverse?: boolean; to?: string }) {
  return (
    <Link aria-label="Alcaldía de Villavicencio — Certificaciones laborales" className="inline-flex min-w-0 items-center gap-3" to={to}>
      <span className={cn('relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border', inverse ? 'border-white/20 bg-white/10 text-white' : 'border-primary/15 bg-primary text-white')}>
        <Landmark aria-hidden="true" size={23} />
        <span aria-hidden="true" className="absolute inset-x-0 bottom-0 flex h-1"><span className="flex-1 bg-[#2878c7]" /><span className="flex-1 bg-[#0b8350]" /><span className="flex-1 bg-[#c72c41]" /></span>
      </span>
      <span className={cn('min-w-0 leading-tight', inverse ? 'text-white' : 'text-text')}>
        <span className="block truncate text-[15px] font-bold tracking-tight">Alcaldía de Villavicencio</span>
        {!compact ? <span className={cn('mt-0.5 block truncate text-xs font-medium', inverse ? 'text-white/70' : 'text-muted')}>Certificaciones laborales</span> : null}
      </span>
    </Link>
  );
}

