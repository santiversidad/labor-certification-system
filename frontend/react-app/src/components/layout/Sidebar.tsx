import { NavLink } from 'react-router-dom';
import { X } from 'lucide-react';
import { env } from '../../config/env';
import { adminNavigation, appNavigation } from '../../config/navigation';
import { useAuth } from '../../features/auth/hooks/useAuth';
import { cn } from '../../lib/utils/cn';
import { Button } from '../ui/Button';

type SidebarProps = {
  mobileOpen?: boolean;
  onClose?: () => void;
  variant?: 'app' | 'admin';
};

export function Sidebar({ mobileOpen = false, onClose, variant = 'app' }: SidebarProps) {
  const { user } = useAuth();
  const items = (variant === 'admin' ? adminNavigation : appNavigation).filter((item) => user && item.roles.includes(user.role));

  const content = (
    <>
      <div className="flex items-start justify-between gap-3 border-b border-border p-5">
        <div>
          <p className="text-sm font-semibold text-govBlue">{env.appName}</p>
          <p className="mt-1 text-xs text-muted">Gestion institucional</p>
        </div>
        {onClose ? <Button aria-label="Cerrar menu" className="lg:hidden" icon={<X size={18} />} onClick={onClose} type="button" variant="ghost" /> : null}
      </div>
      <nav className="space-y-1 p-3">
        {items.map((item) => (
          <NavLink
            className={({ isActive }) =>
              cn(
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted transition hover:bg-background hover:text-text',
                isActive && 'bg-blue-50 text-govBlue',
              )
            }
            key={item.path}
            onClick={onClose}
            to={item.path}
          >
            <item.icon size={18} />
            {item.label}
          </NavLink>
        ))}
      </nav>
    </>
  );

  return (
    <>
      <aside className="hidden w-72 shrink-0 border-r border-border bg-surface lg:block">{content}</aside>
      <div className={cn('fixed inset-0 z-40 bg-black/40 lg:hidden', mobileOpen ? 'block' : 'hidden')} onClick={onClose} />
      <aside className={cn('fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] border-r border-border bg-surface shadow-xl transition-transform lg:hidden', mobileOpen ? 'translate-x-0' : '-translate-x-full')}>
        {content}
      </aside>
    </>
  );
}
