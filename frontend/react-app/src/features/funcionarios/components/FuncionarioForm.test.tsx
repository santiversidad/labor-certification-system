import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { FuncionarioForm } from './FuncionarioForm';
import { dependenciasService } from '../services/dependencias.service';
import { funcionariosService } from '../services/funcionarios.service';

vi.mock('../services/dependencias.service', () => ({ dependenciasService: { list: vi.fn(), cargos: vi.fn(), resolver: vi.fn() } }));
vi.mock('../services/funcionarios.service', () => ({ funcionariosService: { getById: vi.fn(), create: vi.fn(), update: vi.fn() } }));
const a = 'a'.repeat(32), b = 'b'.repeat(32);
const cargo = { id: 41, denominacion: 'Profesional A', codigo: '219', grado: '02', perfiles_count: 1 };
function setup(edit = false) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[edit ? '/edit/7' : '/new']}><Routes><Route path="/new" element={<FuncionarioForm />} /><Route path="/edit/:id" element={<FuncionarioForm />} /><Route path="/admin/funcionarios/:id" element={<p>Guardado</p>} /></Routes></MemoryRouter></QueryClientProvider>);
}
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(dependenciasService.list).mockResolvedValue([{ id: a, nombre: 'Unidad A' }, { id: b, nombre: 'Unidad B' }]);
  vi.mocked(dependenciasService.cargos).mockImplementation(async (unidad) => unidad === a ? [cargo] : [{ ...cargo, id: 52, denominacion: 'Profesional B' }]);
  vi.mocked(dependenciasService.resolver).mockResolvedValue({ source_id: 'TEST-A', funciones_count: 12 });
});
async function seleccionar() {
  await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(await screen.findByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: 'Profesional A · 219/02' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  await screen.findByText(/TEST-A/);
}
it('dependencia es selector cerrado, cargo inicia deshabilitado y no consulta fichas completas', async () => {
  setup();
  expect(screen.getByRole('combobox', { name: 'Dependencia' }).tagName).toBe('SELECT');
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toBeDisabled();
  expect(screen.getByText('Seleccione primero una dependencia...')).toBeVisible();
  await screen.findByRole('option', { name: 'Unidad A' });
  expect(dependenciasService.cargos).not.toHaveBeenCalled();
  expect(screen.queryByLabelText('Ficha del Manual de Funciones')).not.toBeInTheDocument();
});
it('busca dependencia por nombre sin aceptar texto como valor', async () => {
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.type(screen.getByLabelText('Buscar dependencia por nombre'), 'Unidad B');
  expect(screen.queryByRole('option', { name: 'Unidad A' })).not.toBeInTheDocument();
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue('');
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toBeDisabled();
});
it('selección habilita cargo, limita opciones y cambio de unidad limpia cargo y perfil', async () => {
  setup(); await seleccionar();
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toBeEnabled();
  expect(screen.queryByRole('option', { name: /Profesional B/ })).not.toBeInTheDocument();
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), b);
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
  expect(screen.queryByText(/TEST-A/)).not.toBeInTheDocument();
  await screen.findByRole('option', { name: 'Profesional B · 219/02' });
  expect(screen.queryByRole('option', { name: /Profesional A/ })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
});
it('búsqueda de cargo se envía al backend con unidad y fecha', async () => {
  setup(); await seleccionar();
  await userEvent.type(screen.getByLabelText('Buscar cargo por nombre, código o grado'), '219');
  await waitFor(() => expect(dependenciasService.cargos).toHaveBeenLastCalledWith(a, expect.any(String), '219'));
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
});
it('loading y vacío controlados', async () => {
  let resolve!: (items: []) => void;
  vi.mocked(dependenciasService.list).mockReturnValue(new Promise((done) => { resolve = done; }));
  setup(); expect(screen.getByRole('status', { name: '' })).toHaveTextContent('Cargando dependencias...');
  await act(async () => resolve([]));
  expect(await screen.findByText('No se encontraron dependencias.')).toBeVisible();
});
it('error de dependencia ofrece reintento', async () => {
  vi.mocked(dependenciasService.list).mockRejectedValueOnce(new Error('Fallo'));
  setup(); expect(await screen.findByRole('alert')).toHaveTextContent('No se pudieron cargar las dependencias.');
  await userEvent.click(screen.getByRole('button', { name: 'Reintentar carga de dependencia' }));
  await screen.findByRole('option', { name: 'Unidad A' });
});
it('dependencia sin cargos muestra estado vacío', async () => {
  vi.mocked(dependenciasService.cargos).mockResolvedValue([]);
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  expect(await screen.findByText('No hay cargos asociados a esta dependencia.')).toBeVisible();
});
it('error de cargos ofrece reintento sin habilitar guardar', async () => {
  vi.mocked(dependenciasService.cargos).mockRejectedValueOnce(new Error('Fallo de carga'));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  expect(await screen.findByRole('alert')).toHaveTextContent('No se pudieron cargar los cargos.');
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
  await userEvent.click(screen.getByRole('button', { name: 'Reintentar carga de cargo' }));
  await screen.findByRole('option', { name: /Profesional A/ });
});
it('ambigüedad de perfil bloquea guardar y muestra mensaje de dominio', async () => {
  vi.mocked(dependenciasService.resolver).mockRejectedValue(new Error('Hay varios perfiles; se requiere revisión administrativa.'));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Profesional A/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  expect(await screen.findByRole('alert')).toHaveTextContent('Hay varios perfiles');
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
});
it('respuesta tardía del perfil anterior no restaura selección tras cambiar dependencia', async () => {
  let resolve!: (value: { source_id: string; funciones_count: number }) => void;
  vi.mocked(dependenciasService.resolver).mockReturnValue(new Promise((done) => { resolve = done; }));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Profesional A/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  await screen.findByText('Resolviendo perfil funcional...');
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), b);
  await act(async () => resolve({ source_id: 'PERFIL-ANTERIOR', funciones_count: 5 }));
  expect(screen.queryByText(/PERFIL-ANTERIOR/)).not.toBeInTheDocument();
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
});
it('edición precarga valores y preserva ficha histórica sin resolverla nuevamente', async () => {
  vi.mocked(funcionariosService.getById).mockResolvedValue({ success: true, data: { id: 7, tipo_documento: 'CC', numero_documento: '12345678', nombres: 'Persona', apellidos: 'Prueba', estado: 'activo', fecha_ingreso: '2024-01-15', fecha_seleccion: '2026-10-05', unidad_organizacional: { id: a, nombre: 'Unidad A' }, vinculacion_legacy: true, cargo: { ...cargo, nivel: 'Profesional', estado: true }, asignacion_actual: { id: 1, manual_cargo_version_id: 99, ficha_manual: { id: 99, source_id: 'LEGACY-99', area_funcional: 'Unidad A', version: 'Anterior' }, tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa' } } });
  vi.mocked(funcionariosService.update).mockResolvedValue({ success: true, data: { id: 7 } } as never);
  setup(true); await screen.findByText(/LEGACY-99/);
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue(a);
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('41');
  expect(dependenciasService.resolver).not.toHaveBeenCalled();
  await userEvent.click(screen.getByRole('button', { name: 'Guardar funcionario' }));
  await waitFor(() => expect(funcionariosService.update).toHaveBeenCalledWith('7', expect.objectContaining({ unidad_organizacional_id: a, cargo_id: '41' }), true));
});
it('submit envía dependencia/cargo sin ficha y muestra validación requerida', async () => {
  vi.mocked(funcionariosService.create).mockResolvedValue({ success: true, data: { id: 7 } } as never);
  setup();
  fireEvent.change(screen.getByLabelText('Fecha de vinculación'), { target: { value: '2024-01-15' } });
  await screen.findByRole('option', { name: 'Unidad A' }); await seleccionar();
  await userEvent.click(screen.getByRole('button', { name: 'Guardar funcionario' }));
  expect(await screen.findByText('Ingrese documento.')).toBeVisible();
  await userEvent.type(screen.getByLabelText('Número de cédula'), '12345678');
  await userEvent.type(screen.getByLabelText('Nombres'), 'Persona');
  await userEvent.type(screen.getByLabelText('Apellidos'), 'Sintética');
  await userEvent.click(screen.getByRole('button', { name: 'Guardar funcionario' }));
  await waitFor(() => expect(funcionariosService.create).toHaveBeenCalled());
  const values = vi.mocked(funcionariosService.create).mock.calls[0][0];
  expect(values).toMatchObject({ unidad_organizacional_id: a, cargo_id: '41' });
  expect(values).not.toHaveProperty('manual_cargo_version_id');
  expect(values).not.toHaveProperty('dependencia');
});
