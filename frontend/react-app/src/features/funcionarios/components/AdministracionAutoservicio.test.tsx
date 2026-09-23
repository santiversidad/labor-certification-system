import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import { FuncionariosPage } from '../pages/FuncionariosPage';
import { funcionariosService } from '../services/funcionarios.service';
import { cargosService } from '../../cargos/services/cargos.service';
import { ConfiguracionCertificacionesPage } from '../../configuracion/pages/ConfiguracionCertificacionesPage';
import { configuracionService } from '../../configuracion/services/configuracion.service';

vi.mock('../services/funcionarios.service', () => ({ funcionariosService: { list: vi.fn(), resetAccess: vi.fn(), setEstado: vi.fn() } }));
vi.mock('../../cargos/services/cargos.service', () => ({ cargosService: { options: vi.fn() } }));
vi.mock('../../configuracion/services/configuracion.service', () => ({ configuracionService: { get: vi.fn(), update: vi.fn() } }));
function setup(node: ReactNode) {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter>{node}</MemoryRouter></QueryClientProvider>);
}
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(cargosService.options).mockResolvedValue({ success: true, data: [] });
  vi.mocked(funcionariosService.list).mockResolvedValue({ success: true, data: [{ id: 7, tipo_documento: 'CC', numero_documento: '123456', nombres: 'Ana', apellidos: 'Prueba', estado: 'activo', usuario: { id: 8, estado: true, must_change_password: true } }], meta: { current_page: 1, per_page: 15, total: 16, last_page: 2 } });
});
it('listado muestra estado de acceso, permite alta y pagina en servidor', async () => {
  setup(<FuncionariosPage />);
  expect(await screen.findByText('123456')).toBeInTheDocument();
  expect(screen.getByText('Cambio pendiente')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Crear funcionario' })).toHaveAttribute('href', '/admin/funcionarios/nuevo');
  await userEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
  await waitFor(() => expect(funcionariosService.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2, per_page: 15 })));
});
it('reset requiere confirmación y no muestra contraseñas', async () => {
  vi.mocked(funcionariosService.resetAccess).mockResolvedValue();
  setup(<FuncionariosPage />);
  await userEvent.click(await screen.findByRole('button', { name: 'Restablecer acceso' }));
  const dialog = screen.getByRole('dialog', { name: 'Restablecer acceso' });
  expect(dialog).toBeInTheDocument();
  await userEvent.click(within(dialog).getByRole('button', { name: 'Restablecer acceso' }));
  await waitFor(() => expect(funcionariosService.resetAccess).toHaveBeenCalledWith(7, expect.anything()));
  expect(await screen.findByRole('status')).toHaveTextContent('Acceso restablecido');
});
it('administrador activa parámetro de pago', async () => {
  vi.mocked(configuracionService.get).mockResolvedValue({ success: true, data: { requiere_pago_certificado: false, descripcion: 'Expedición inmediata' } });
  vi.mocked(configuracionService.update).mockResolvedValue({ success: true, data: { requiere_pago_certificado: true, descripcion: 'Pendiente' } });
  setup(<ConfiguracionCertificacionesPage />);
  await userEvent.click(await screen.findByRole('switch', { name: 'Exigir pago para certificaciones' }));
  const dialog = screen.getByRole('dialog', { name: 'Activar pago de certificaciones' });
  await userEvent.click(within(dialog).getByRole('button', { name: 'Activar pago' }));
  await waitFor(() => expect(configuracionService.update).toHaveBeenCalledWith(true, expect.anything()));
});
