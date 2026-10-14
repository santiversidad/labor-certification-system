import { NavLink } from 'react-router-dom';
import { X } from 'lucide-react';
import { adminNavigation, appNavigation } from '../../config/navigation';
import { useAuth } from '../../features/auth/hooks/useAuth';
import { hasAnyRole } from '../../lib/auth/permissions';
import { cn } from '../../lib/utils/cn';
import { Button } from '../ui/Button';
import { Brand } from './Brand';

type SidebarProps = {
  mobileOpen?: boolean;
  onClose?: () => void;
  variant?: 'app' | 'admin';
};

export function Sidebar({ mobileOpen = false, onClose, variant = 'app' }: SidebarProps) {
  const { user } = useAuth();
  const items = (variant === 'admin' ? adminNavigation : appNavigation).filter((item) => hasAnyRole(user, item.roles));

  const content = (
    <>
      <div className="flex items-start justify-between gap-3 border-b border-white/10 p-5">
        <Brand inverse to="/admin/dashboard" />
        {onClose ? <Button aria-label="Cerrar menú" className="text-white hover:bg-white/10 lg:hidden" icon={<X size={18} />} onClick={onClose} type="button" variant="ghost" /> : null}
      </div>
      <nav aria-label="Navegación administrativa" className="flex-1 space-y-1 p-3">
        {items.map((item) => (
          <NavLink
            className={({ isActive }) =>
              cn(
                'flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition',
                isActive ? 'bg-white text-primary shadow-sm hover:bg-white' : 'text-slate-300 hover:bg-white/10 hover:text-white',
              )
            }
            key={item.path}
            onClick={onClose}
            to={item.path}
          >
            <item.icon aria-hidden="true" size={18} />
            {item.label}
          </NavLink>
        ))}
      </nav>
    </>
  );

  return (
    <>
      <aside className="hidden w-72 shrink-0 flex-col bg-[#10294c] lg:flex">{content}</aside>
      <div className={cn('fixed inset-0 z-40 bg-black/40 lg:hidden', mobileOpen ? 'block' : 'hidden')} onClick={onClose} />
      <aside className={cn('fixed inset-y-0 left-0 z-50 flex w-80 max-w-[85vw] flex-col bg-[#10294c] shadow-raised transition-transform lg:hidden', mobileOpen ? 'translate-x-0' : '-translate-x-full')}>
        {content}
      </aside>
    </>
  );
}
