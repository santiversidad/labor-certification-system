import { LogOut, Menu } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../ui/Button';
import { roleLabels } from '../../config/roles';
import { useAuth } from '../../features/auth/hooks/useAuth';

export function Header({ onMenuClick }: { onMenuClick?: () => void }) {
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <header className="flex min-h-16 items-center justify-between border-b border-border bg-surface px-6">
      <div className="flex min-w-0 items-center gap-3">
        <Button aria-label="Abrir menu" className="lg:hidden" icon={<Menu size={18} />} onClick={onMenuClick} type="button" variant="secondary" />
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-text">{user?.name ?? 'Usuario'}</p>
          <p className="truncate text-xs text-muted">{user ? roleLabels[user.role] : 'Sin rol'}</p>
        </div>
      </div>
      <Button icon={<LogOut size={16} />} onClick={handleLogout} type="button" variant="secondary">
        Salir
      </Button>
    </header>
  );
}
