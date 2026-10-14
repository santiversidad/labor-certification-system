import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import { apiClient } from '../../lib/api/apiClient';
import type { User } from '../../types/common.types';
import { BackofficeUsersPage } from './BackofficeUsersPage';

vi.mock('../../lib/api/apiClient', () => ({ apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));
const user: User = { id: 8, name: 'María Test', username: 'maria.test', tipo_cuenta: 'backoffice', documento: null,
  roles: ['secretario'], permisos: [], estado: true, must_change_password: false, created_at: '2026-10-04T12:00:00Z' };
const temporary = 'TemporalSynthetic#2026!';

beforeEach(() => {
  vi.resetAllMocks();
  vi.mocked(apiClient.get).mockResolvedValue({ data: { success: true, data: [user], meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } } });
  vi.mocked(apiClient.post).mockResolvedValue({ data: { success: true, data: { user, temporary_password: temporary } } });
});

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(<QueryClientProvider client={client}><BackofficeUsersPage /></QueryClientProvider>);
  return client;
}

it('listado muestra identidades internas y filtros de servidor', async () => {
  setup();
  expect(await screen.findByText('maria.test')).toBeVisible();
  expect(screen.getByText('Sin acceso registrado')).toBeVisible();
  fireEvent.change(screen.getByLabelText('Estado'), { target: { value: '0' } });
  await waitFor(() => expect(apiClient.get).toHaveBeenLastCalledWith('/admin/usuarios-internos', expect.objectContaining({ params: expect.objectContaining({ estado: '0' }) })));
});

it('crear usa datos independientes del funcionario y descarta el secreto al cerrar', async () => {
  const client = setup();
  await userEvent.click(screen.getByRole('button', { name: 'Crear usuario interno' }));
  await userEvent.type(screen.getByLabelText('Nombre descriptivo'), 'María Test');
  await userEvent.type(screen.getByLabelText('Usuario institucional individual'), 'maria.test');
  expect(screen.queryByLabelText(/cédula|cargo|funcionario/i)).not.toBeInTheDocument();
  await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }));
  const field = await screen.findByLabelText('Contraseña temporal generada');
  expect(field).toHaveValue(temporary);
  expect(apiClient.post).toHaveBeenCalledWith('/admin/usuarios-internos', { name: 'María Test', username: 'maria.test', role: 'secretario', email: null });
  expect(JSON.stringify(client.getQueryCache().getAll().map((query) => query.state.data))).not.toContain(temporary);
  expect(localStorage.length).toBe(0);
  expect(sessionStorage.length).toBe(0);
  await userEvent.click(screen.getByRole('button', { name: 'Cerrar y descartar' }));
  expect(screen.queryByDisplayValue(temporary)).not.toBeInTheDocument();
});

it('desactivar requiere motivo y explica la revocación', async () => {
  setup();
  await userEvent.click(await screen.findByRole('button', { name: 'Desactivar acceso' }));
  expect(screen.getByText(/cerrará las sesiones activas/)).toBeVisible();
  expect(screen.getByLabelText('Motivo de desactivación')).toBeRequired();
  await userEvent.type(screen.getByLabelText('Motivo de desactivación'), 'Fin del cargo ficticio');
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar cambio' }));
  await waitFor(() => expect(apiClient.post).toHaveBeenCalledWith('/admin/usuarios-internos/8/desactivar', { motivo: 'Fin del cargo ficticio' }));
});

it('último administrador muestra rechazo del backend', async () => {
  vi.mocked(apiClient.post).mockRejectedValue({ response: { data: { code: 'LAST_ACTIVE_ADMIN', message: 'Debe conservar al menos un administrador activo.' } } });
  setup();
  await userEvent.click(await screen.findByRole('button', { name: 'Cambiar rol' }));
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar cambio' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Debe conservar al menos un administrador activo.');
});

it('editar conserva el username y no ofrece campos laborales', async () => {
  setup();
  await userEvent.click(await screen.findByRole('button', { name: 'Editar' }));
  expect(screen.queryByLabelText('Usuario institucional individual')).not.toBeInTheDocument();
  await userEvent.clear(screen.getByLabelText('Nombre descriptivo'));
  await userEvent.type(screen.getByLabelText('Nombre descriptivo'), 'María Nueva Test');
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar cambio' }));
  await waitFor(() => expect(apiClient.patch).toHaveBeenCalledWith('/admin/usuarios-internos/8', { name: 'María Nueva Test', email: null }));
});

it('modal mantiene foco con teclado y permite cierre con Escape', async () => {
  setup();
  await userEvent.click(screen.getByRole('button', { name: 'Crear usuario interno' }));
  const panel = screen.getByRole('dialog');
  panel.focus();
  fireEvent.keyDown(panel, { key: 'Tab', shiftKey: true });
  expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus();
  fireEvent.keyDown(screen.getByRole('button', { name: 'Cancelar' }), { key: 'Tab' });
  expect(screen.getByRole('button', { name: 'Cerrar modal' })).toHaveFocus();
  await userEvent.keyboard('{Escape}');
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
});

it('copiar sin Clipboard API ofrece selección con teclado sin perder el secreto', async () => {
  setup();
  await userEvent.click(await screen.findByRole('button', { name: 'Restablecer contraseña' }));
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar cambio' }));
  const field = await screen.findByLabelText('Contraseña temporal generada');
  const descriptor = Object.getOwnPropertyDescriptor(navigator, 'clipboard');
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
  try {
    fireEvent.click(screen.getByRole('button', { name: 'Copiar contraseña' }));
    expect(await screen.findByText(/Seleccione la contraseña y cópiela con el teclado/)).toBeVisible();
    expect(field).toHaveValue(temporary);
    field.focus();
    expect(field).toHaveFocus();
  } finally {
    if (descriptor) Object.defineProperty(navigator, 'clipboard', descriptor);
    else Reflect.deleteProperty(navigator, 'clipboard');
  }
});
