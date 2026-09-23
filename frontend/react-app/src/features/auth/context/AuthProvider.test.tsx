import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ProtectedRoute } from '../../../components/guards/ProtectedRoute';
import { RoleRoute } from '../../../components/guards/RoleRoute';
import { authStorage } from '../../../lib/auth/authStorage';
import type { User } from '../../../types/common.types';
import { cargosService } from '../../cargos/services/cargos.service';
import { FuncionariosPage } from '../../funcionarios/pages/FuncionariosPage';
import { funcionariosService } from '../../funcionarios/services/funcionarios.service';
import { authService } from '../services/auth.service';
import { useAuth } from '../hooks/useAuth';
import { AuthProvider } from './AuthProvider';

vi.mock('../services/auth.service', () => ({ authService: { me: vi.fn() } }));
vi.mock('../../funcionarios/services/funcionarios.service', () => ({
  funcionariosService: { list: vi.fn(), resetAccess: vi.fn(), setEstado: vi.fn() },
}));
vi.mock('../../cargos/services/cargos.service', () => ({
  cargosService: { options: vi.fn() },
}));

const admin: User = {
  id: 1,
  name: 'Admin cacheado',
  documento: '1',
  estado: true,
  must_change_password: false,
  roles: ['admin'],
  permisos: ['funcionarios.ver'],
};
const funcionario: User = {
  ...admin,
  id: 2,
  name: 'Funcionario real',
  documento: '2',
  roles: ['funcionario'],
  permisos: ['solicitudes.crear'],
};

function renderRoutes(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={[path]}>
          <Routes>
            <Route path="/login" element={<h1>Ingreso</h1>} />
            <Route path="/app/inicio" element={<ProtectedRoute><h1>Inicio funcionario</h1></ProtectedRoute>} />
            <Route path="/admin/funcionarios" element={
              <ProtectedRoute>
                <RoleRoute allowedRoles={['admin']}><FuncionariosPage /></RoleRoute>
              </ProtectedRoute>
            } />
          </Routes>
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  );
}

function LogoutButton() {
  const { logout } = useAuth();
  return <button onClick={logout} type="button">Salir</button>;
}

describe('revalidación de identidad', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    authStorage.setSession({ token: 'token-prueba', user: admin });
  });

  it('reemplaza el admin cacheado por el funcionario real sin disparar queries administrativas', async () => {
    vi.mocked(authService.me).mockResolvedValue({ success: true, data: funcionario });

    renderRoutes('/admin/funcionarios');

    expect(screen.getByText('Validando sesión...')).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Inicio funcionario' })).toBeInTheDocument();
    expect(authStorage.getUser()?.roles).toEqual(['funcionario']);
    expect(funcionariosService.list).not.toHaveBeenCalled();
    expect(cargosService.options).not.toHaveBeenCalled();
  });

  it('confirma al admin antes de renderizar funcionarios y consultar el backend', async () => {
    vi.mocked(authService.me).mockResolvedValue({ success: true, data: admin });
    vi.mocked(funcionariosService.list).mockResolvedValue({
      success: true,
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    });
    vi.mocked(cargosService.options).mockResolvedValue({ success: true, data: [] });

    renderRoutes('/admin/funcionarios');

    expect(await screen.findByRole('heading', { name: 'Funcionarios' })).toBeInTheDocument();
    expect(await screen.findByRole('table', { name: 'Listado de funcionarios' })).toBeInTheDocument();
    await waitFor(() => expect(funcionariosService.list).toHaveBeenCalledTimes(1));
    expect(screen.queryByText(/rol no autorizado/i)).not.toBeInTheDocument();
  });

  it('elimina consultas dependientes de la identidad al cerrar sesión', async () => {
    vi.mocked(authService.me).mockResolvedValue({ success: true, data: admin });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    client.setQueryData(['disponibilidad-certificacion'], { userId: 2 });

    render(
      <QueryClientProvider client={client}>
        <AuthProvider><LogoutButton /></AuthProvider>
      </QueryClientProvider>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
    await waitFor(() => expect(authStorage.getSession()).toBeNull());
    expect(client.getQueryData(['disponibilidad-certificacion'])).toBeUndefined();
  });
});
