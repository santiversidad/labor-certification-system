import { render, screen, waitFor } from '@testing-library/react';
import { StrictMode } from 'react';
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
  render(<StrictMode><MemoryRouter initialEntries={['/seguridad/mfa']}><Routes>
    <Route path="/seguridad/mfa" element={<MfaPage />} /><Route path="/admin/dashboard" element={<h1>Panel seguro</h1>} />
    <Route path="/login" element={<h1>Ingreso</h1>} />
  </Routes></MemoryRouter></StrictMode>);
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
  vi.mocked(authService.setupMfa).mockResolvedValue(setupFixture);
  vi.mocked(authService.verifyMfa).mockResolvedValue({ success: true, data: { user: { ...user, mfa_pending: false }, recovery_codes: ['synthetic-one', 'synthetic-two'] } });
  renderMfa(); await screen.findByRole('img', { name: /Código QR/ });
  await userEvent.click(screen.getByText('No puedo escanear el código'));
  expect(screen.getByLabelText('Clave de configuración')).toHaveValue('SYNTHETIC-SETUP');
  await userEvent.type(screen.getByLabelText('Código de 6 dígitos'), '123456');
  await userEvent.click(screen.getByRole('button', { name: 'Activar autenticación en dos pasos' }));
  expect(await screen.findByRole('dialog', { name: 'Códigos de recuperación' })).toBeInTheDocument();
  expect(localStorage.length).toBe(0); expect(sessionStorage.length).toBe(0);
  await userEvent.click(screen.getByRole('button', { name: 'Ya los guardé, continuar' }));
  expect(await screen.findByText('Panel seguro')).toBeInTheDocument();
  expect(screen.queryByText('synthetic-one')).not.toBeInTheDocument();
});

// Deliberately non-Base32 material: visual/unit fixture cannot enroll a real account.
const setupFixture = { success: true, data: { secret: 'SYNTHETIC-SETUP', issuer: 'Test', account: 'person.test',
  otpauth_uri: 'otpauth://totp/Test:visual-only?secret=SYNTHETIC-SETUP&issuer=Test&algorithm=SHA1&digits=6&period=30' } };
function prepareEnrollment() {
  authStorage.setSession({ user: { ...user, mfa_enabled: false } });
  vi.mocked(authService.setupMfa).mockResolvedValue(setupFixture);
}
it('explica tres pasos, renderiza SVG local y oculta la alternativa manual inicialmente', async () => {
  prepareEnrollment(); renderMfa();
  expect(screen.getByText(/Además de su contraseña/)).toBeVisible();
  expect(screen.getByRole('heading', { name: 'Abra una aplicación de autenticación' })).toBeVisible();
  expect(screen.getByText(/Google Authenticator, Microsoft Authenticator, 2FAS/)).toBeVisible();
  const qr = await screen.findByRole('img', { name: /Código QR/ });
  expect(qr.querySelector('svg path')).not.toBeNull();
  expect(qr.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  expect(document.querySelector('img[src*="otpauth"]')).toBeNull();
  expect(screen.getByLabelText('Clave de configuración')).not.toBeVisible();
  await userEvent.click(screen.getByText('No puedo escanear el código'));
  expect(screen.getByLabelText('Clave de configuración')).toBeVisible();
  await userEvent.click(screen.getByText('No puedo escanear el código'));
  expect(screen.getByLabelText('Clave de configuración')).not.toBeVisible();
  expect(authService.setupMfa).toHaveBeenCalledTimes(1);
});
it('copia la clave solo por acción explícita e informa el resultado', async () => {
  const interactions = userEvent.setup();
  const write = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
  prepareEnrollment(); renderMfa(); await screen.findByTestId('mfa-qr');
  expect(write).not.toHaveBeenCalled();
  await interactions.click(screen.getByText('No puedo escanear el código'));
  await interactions.click(screen.getByRole('button', { name: 'Copiar clave' }));
  expect(write).toHaveBeenCalledWith('SYNTHETIC-SETUP');
  expect(await screen.findByText('Clave copiada')).toHaveAttribute('role', 'status');
  write.mockRestore();
});
it('informa cómo copiar manualmente cuando el portapapeles falla', async () => {
  const interactions = userEvent.setup();
  const write = vi.spyOn(navigator.clipboard, 'writeText').mockRejectedValue(new Error('Clipboard unavailable'));
  prepareEnrollment(); renderMfa(); await screen.findByTestId('mfa-qr');
  await interactions.click(screen.getByText('No puedo escanear el código'));
  await interactions.click(screen.getByRole('button', { name: 'Copiar clave' }));
  expect(await screen.findByText('Seleccione y copie la clave con el teclado.')).toBeVisible();
  write.mockRestore();
});
it('permite pegar seis dígitos y asocia los errores comprensibles al campo', async () => {
  prepareEnrollment(); renderMfa(); await screen.findByTestId('mfa-qr');
  const input = screen.getByLabelText('Código de 6 dígitos');
  expect(input).toHaveAttribute('inputmode', 'numeric');
  expect(input).toHaveAttribute('autocomplete', 'one-time-code');
  await userEvent.click(screen.getByRole('button', { name: 'Activar autenticación en dos pasos' }));
  expect(input).toHaveAccessibleDescription('Ingrese el código de 6 dígitos que aparece en su aplicación.');
  expect(input).toHaveFocus(); expect(authService.verifyMfa).not.toHaveBeenCalled();
  await userEvent.type(input, 'abc'); expect(input).toHaveValue('');
  await userEvent.paste('123456'); expect(input).toHaveValue('123456');
  vi.mocked(authService.verifyMfa).mockRejectedValue({ response: { status: 422, data: { message: 'Invalid TOTP' } } });
  await userEvent.click(screen.getByRole('button', { name: 'Activar autenticación en dos pasos' }));
  await waitFor(() => expect(input).toHaveAccessibleDescription(/El código no es válido o ya cambió/));
  expect(authService.verifyMfa).toHaveBeenCalledWith('123456', true);
  expect(screen.queryByText('Invalid TOTP')).not.toBeInTheDocument();
});
it('bloquea activación hasta cargar setup y mientras verifica', async () => {
  prepareEnrollment();
  let completeSetup!: (value: typeof setupFixture) => void;
  vi.mocked(authService.setupMfa).mockImplementation(() => new Promise((resolve) => { completeSetup = resolve; }));
  renderMfa(); expect(screen.getByText('Preparando el código QR…')).toHaveAttribute('role', 'status');
  expect(screen.getByRole('button', { name: 'Activar autenticación en dos pasos' })).toBeDisabled();
  completeSetup(setupFixture); await screen.findByTestId('mfa-qr');
  vi.mocked(authService.verifyMfa).mockImplementation(() => new Promise(() => {}));
  await userEvent.type(screen.getByLabelText('Código de 6 dígitos'), '123456');
  await userEvent.click(screen.getByRole('button', { name: 'Activar autenticación en dos pasos' }));
  expect(screen.getByRole('button', { name: 'Verificando…' })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'Cancelar y cerrar sesión' })).toBeDisabled();
});
it('permite reintentar setup sin enviar datos sensibles al error', async () => {
  prepareEnrollment();
  vi.mocked(authService.setupMfa).mockRejectedValueOnce(new Error('No fue posible preparar la configuración.'));
  renderMfa(); await userEvent.click(await screen.findByRole('button', { name: 'Volver a intentar' }));
  expect(await screen.findByTestId('mfa-qr')).toBeVisible();
  expect(authService.setupMfa).toHaveBeenCalledTimes(2);
});
it('cancelar exige logout exitoso y descarta el material al salir', async () => {
  prepareEnrollment(); vi.mocked(authService.logout).mockRejectedValueOnce(new Error('Offline')).mockResolvedValue();
  renderMfa(); await screen.findByTestId('mfa-qr');
  await userEvent.click(screen.getByRole('button', { name: 'Cancelar y cerrar sesión' }));
  expect(await screen.findByText(/No fue posible cerrar la sesión/)).toBeVisible();
  expect(screen.getByTestId('mfa-qr')).toBeVisible();
  await userEvent.click(screen.getByRole('button', { name: 'Cancelar y cerrar sesión' }));
  expect(await screen.findByText('Ingreso')).toBeVisible();
  expect(authStorage.getSession()).toBeNull();
  expect(screen.queryByTestId('mfa-qr')).not.toBeInTheDocument();
  expect(screen.queryByLabelText('Clave de configuración')).not.toBeInTheDocument();
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
