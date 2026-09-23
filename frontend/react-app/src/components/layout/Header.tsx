import { LogOut, Menu } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../ui/Button';
import { roleLabels } from '../../config/roles';
import { getPrimaryRole } from '../../lib/auth/permissions';
import { useAuth } from '../../features/auth/hooks/useAuth';
import { authService } from '../../features/auth/services/auth.service';
import { useState } from 'react';
import { Brand } from './Brand';

export function Header({ onMenuClick, variant = 'app' }: { onMenuClick?: () => void; variant?: 'app' | 'admin' }) {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const primaryRole = getPrimaryRole(user);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  async function handleLogout() {
    setIsLoggingOut(true);
    try {
      await authService.logout();
    } finally {
      logout();
      navigate('/login', { replace: true });
    }
  }

  return (
    <header className="sticky top-0 z-30 flex min-h-[72px] items-center justify-between gap-4 border-b border-border bg-surface/95 px-4 backdrop-blur sm:px-6">
      <div className="flex min-w-0 items-center gap-3">
        {variant === 'admin' ? <Button aria-label="Abrir menú" className="lg:hidden" icon={<Menu size={18} />} onClick={onMenuClick} type="button" variant="secondary" /> : <Brand compact to="/app/inicio" />}
        {variant === 'admin' ? <div className="hidden min-w-0 sm:block"><p className="text-sm font-bold text-text">Administración</p><p className="text-xs text-muted">Certificaciones laborales</p></div> : null}
      </div>
      <div className="flex min-w-0 items-center gap-3">
        <div className="hidden min-w-0 text-right sm:block"><p className="truncate text-sm font-semibold text-text">{user?.name ?? 'Usuario'}</p><p className="truncate text-xs text-muted">{primaryRole ? roleLabels[primaryRole] : 'Sin rol'}</p></div>
        <Button aria-label="Cerrar sesión" disabled={isLoggingOut} icon={<LogOut size={16} />} onClick={handleLogout} type="button" variant="secondary"><span className="hidden sm:inline">{isLoggingOut ? 'Saliendo...' : 'Cerrar sesión'}</span></Button>
      </div>
    </header>
  );
}
