import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { ProtectedRoute } from '../../../components/guards/ProtectedRoute';
import { authStorage } from '../../../lib/auth/authStorage';
import type { User } from '../../../types/common.types';
import { LoginForm } from '../components/LoginForm';
import { authService } from '../services/auth.service';
import { ChangePasswordPage } from './ChangePasswordPage';

vi.mock('../services/auth.service', () => ({ authService: { login: vi.fn(), changePassword: vi.fn() } }));
const user: User = { id: 1, name: 'Ana', documento: '123456', estado: true, must_change_password: true, roles: ['funcionario'], permisos: [] };

function setup(path: string) {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    <MemoryRouter initialEntries={[path]}><Routes>
      <Route path="/login" element={<LoginForm />} />
      <Route path="/app/inicio" element={<ProtectedRoute><h1>Dashboard habilitado</h1></ProtectedRoute>} />
      <Route path="/cambiar-contrasena" element={<ProtectedRoute><ChangePasswordPage /></ProtectedRoute>} />
    </Routes></MemoryRouter>
  </QueryClientProvider>);
}
beforeEach(() => { vi.clearAllMocks(); localStorage.clear(); });

it('primer login redirige sin mostrar dashboard', async () => {
  vi.mocked(authService.login).mockResolvedValue({ success: true, data: { token: 'token', user } });
  setup('/login');
  await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }));
  expect(await screen.findByText('Cambie su contraseña temporal')).toBeInTheDocument();
  expect(screen.queryByText('Dashboard habilitado')).not.toBeInTheDocument();
});

it('URL directa bloqueada; cambiar contraseña habilita dashboard y actualiza sesión', async () => {
  authStorage.setSession({ token: 'token', user });
  vi.mocked(authService.changePassword).mockResolvedValue({ success: true, data: { ...user, must_change_password: false } });
  setup('/app/inicio');
  expect(await screen.findByText('Cambie su contraseña temporal')).toBeInTheDocument();
  await userEvent.type(screen.getByLabelText('Contraseña temporal'), '123456');
  await userEvent.type(screen.getByLabelText('Nueva contraseña', { exact: true }), 'MiClaveNueva2026');
  await userEvent.type(screen.getByLabelText('Confirmar nueva contraseña'), 'MiClaveNueva2026');
  await userEvent.click(screen.getByRole('button', { name: 'Guardar y continuar' }));
  expect(await screen.findByText('Dashboard habilitado')).toBeInTheDocument();
  expect(authStorage.getSession()?.user.must_change_password).toBe(false);
  expect(authService.changePassword).toHaveBeenCalledWith({ current_password: '123456', password: 'MiClaveNueva2026', password_confirmation: 'MiClaveNueva2026' }, expect.anything());
});

it('sesión con cambio completado permite dashboard', () => {
  authStorage.setSession({ token: 'token', user: { ...user, must_change_password: false } });
  setup('/app/inicio');
  expect(screen.getByText('Dashboard habilitado')).toBeInTheDocument();
});
