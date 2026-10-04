import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { authStorage } from '../../../lib/auth/authStorage';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import type { User } from '../../../types/common.types';
import { authService } from '../services/auth.service';
import { MfaPage } from './MfaPage';
import { RecoveryCodesModal } from '../components/RecoveryCodesModal';

vi.mock('../services/auth.service', () => ({ authService: { setupMfa: vi.fn(), verifyMfa: vi.fn(), logout: vi.fn() } }));
const user: User = { id: 3, name: 'Persona sintética', username: 'person.test', documento: null, tipo_cuenta: 'backoffice', estado: true,
  must_change_password: false, mfa_pending: true, mfa_enabled: true, roles: ['admin'], permisos: [] };
function renderMfa() {
  render(<MemoryRouter initialEntries={['/seguridad/mfa']}><Routes>
    <Route path="/seguridad/mfa" element={<MfaPage />} /><Route path="/admin/dashboard" element={<h1>Panel seguro</h1>} />
  </Routes></MemoryRouter>);
}
beforeEach(() => { vi.clearAllMocks(); authStorage.clearSession(); localStorage.clear(); sessionStorage.clear(); });

it('no persiste identidad ni credenciales y descarta el bearer legacy', () => {
  localStorage.setItem('clv_session', JSON.stringify({ token: 'synthetic-legacy-token', user }));
  authStorage.removeLegacySession(); authStorage.setSession({ user });
  expect(localStorage.length).toBe(0); expect(sessionStorage.length).toBe(0);
  expect(authStorage.getSession()).toEqual({ user });
});
it('redirige cambio obligatorio antes de MFA y MFA antes del panel', () => {
  expect(getHomePath({ ...user, must_change_password: true })).toBe('/cambiar-contrasena');
  expect(getHomePath(user)).toBe('/seguridad/mfa');
  expect(getHomePath({ ...user, mfa_pending: false })).toBe('/admin/dashboard');
});
it('permite pegar TOTP y solo abre el panel después de verificar', async () => {
  authStorage.setSession({ user });
  vi.mocked(authService.verifyMfa).mockResolvedValue({ success: true, data: { user: { ...user, mfa_pending: false }, recovery_codes: [] } });
  renderMfa();
  await userEvent.click(screen.getByLabelText('Código de autenticación'));
  await userEvent.paste('123456');
  await userEvent.click(screen.getByRole('button', { name: 'Verificar y continuar' }));
  expect(await screen.findByText('Panel seguro')).toBeInTheDocument();
  expect(authService.verifyMfa).toHaveBeenCalledWith('123456', false);
});
it('el código de recuperación usa el mismo desafío y no storage', async () => {
  authStorage.setSession({ user });
  vi.mocked(authService.verifyMfa).mockResolvedValue({ success: true, data: { user: { ...user, mfa_pending: false }, recovery_codes: [] } });
  renderMfa(); await userEvent.click(screen.getByRole('button', { name: 'Usar código de recuperación' }));
  await userEvent.type(screen.getByLabelText('Código de recuperación'), 'synthetic-recovery');
  await userEvent.click(screen.getByRole('button', { name: 'Verificar y continuar' }));
  await waitFor(() => expect(authService.verifyMfa).toHaveBeenCalledWith('synthetic-recovery', false));
  expect(localStorage.length).toBe(0); expect(sessionStorage.length).toBe(0);
});
it('setup permanece en memoria y los códigos se muestran una vez en modal', async () => {
  authStorage.setSession({ user: { ...user, mfa_enabled: false } });
  vi.mocked(authService.setupMfa).mockResolvedValue({ success: true, data: { secret: 'SYNTHETIC-SETUP', issuer: 'Test', account: 'person.test' } });
  vi.mocked(authService.verifyMfa).mockResolvedValue({ success: true, data: { user: { ...user, mfa_pending: false }, recovery_codes: ['synthetic-one', 'synthetic-two'] } });
  renderMfa(); expect(await screen.findByLabelText('Clave de configuración')).toHaveValue('SYNTHETIC-SETUP');
  await userEvent.type(screen.getByLabelText('Código de autenticación'), '123456');
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar autenticación' }));
  expect(await screen.findByRole('dialog', { name: 'Códigos de recuperación' })).toBeInTheDocument();
  expect(localStorage.length).toBe(0); expect(sessionStorage.length).toBe(0);
  await userEvent.click(screen.getByRole('button', { name: 'Ya los guardé, continuar' }));
  expect(await screen.findByText('Panel seguro')).toBeInTheDocument();
  expect(screen.queryByText('synthetic-one')).not.toBeInTheDocument();
});
it('recovery modal escapes synthetic XSS and allows keyboard dismissal', async () => {
  const close = vi.fn(); render(<RecoveryCodesModal codes={['<img src=x onerror=alert(1)>']} onClose={close} />);
  expect(screen.getByTestId('recovery-codes')).toHaveTextContent('<img src=x onerror=alert(1)>');
  expect(document.querySelector('img[src="x"]')).toBeNull();
  await userEvent.keyboard('{Escape}'); expect(close).toHaveBeenCalled();
});
it('technical errors do not expose SQL paths or class names', () => {
  for (const message of ['SQLSTATE[bad]', 'C:\\private\\secret.php', 'Illuminate\\Database\\Error', 'App\\Services\\Secret']) {
    expect(getErrorMessage({ response: { data: { message } } })).toBe('No fue posible completar la operación. Intente nuevamente o contacte a soporte.');
  }
});
