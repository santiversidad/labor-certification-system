import { Link } from 'react-router-dom';
import { cn } from '../../lib/utils/cn';

export function Brand({ compact = false, inverse = false, to = '/' }: { compact?: boolean; inverse?: boolean; to?: string }) {
  return (
    <Link aria-label="Alcaldía de Villavicencio — Certificaciones laborales" className="inline-flex min-w-0 items-center gap-3" to={to}>
      <img alt="" className="h-12 w-11 shrink-0 object-contain" height={842} src="/assets/brand/escudo-villavicencio.webp" width={768} />
      <span className={cn('min-w-0 leading-tight', inverse ? 'text-white' : 'text-text')}>
        <span className="block text-[15px] font-bold tracking-tight">Alcaldía de Villavicencio</span>
        {!compact ? <span className={cn('mt-0.5 block text-xs font-medium', inverse ? 'text-white/85' : 'text-muted')}>Sistema de Certificaciones Laborales</span> : null}
      </span>
    </Link>
  );
}
