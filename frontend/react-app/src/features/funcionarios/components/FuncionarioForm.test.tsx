import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { FuncionarioForm } from './FuncionarioForm';
import { dependenciasService } from '../services/dependencias.service';
import { funcionariosService } from '../services/funcionarios.service';

vi.mock('../services/dependencias.service', () => ({ dependenciasService: { list: vi.fn(), cargos: vi.fn(), resolver: vi.fn(), funciones: vi.fn(), funcionesRegistradas: vi.fn() } }));
vi.mock('../services/funcionarios.service', () => ({ funcionariosService: { getById: vi.fn(), create: vi.fn(), update: vi.fn() } }));
const a = 'a'.repeat(32), b = 'b'.repeat(32);
const cargo = { id: 41, denominacion: 'Profesional A', codigo: '219', grado: '02', perfiles_count: 1 };
function setup(edit = false) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[edit ? '/edit/7' : '/new']}><Routes><Route path="/new" element={<FuncionarioForm />} /><Route path="/edit/:id" element={<FuncionarioForm />} /><Route path="/admin/funcionarios/:id" element={<p>Guardado</p>} /></Routes></MemoryRouter></QueryClientProvider>);
}
function apiError(code?: string, status = 422) {
  return { isAxiosError: true, response: { status, data: { code, message: 'Texto que no se interpreta', errors: { cargo_id: ['Texto que no se interpreta'] } } } };
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
  await userEvent.type(screen.getByLabelText('Buscar cargo por nombre, código, grado o página'), '219');
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
  vi.mocked(dependenciasService.resolver).mockRejectedValue(apiError('MANUAL_FICHA_AMBIGUA'));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Profesional A/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  expect(await screen.findByRole('alert')).toHaveTextContent('Este cargo requiere revisión del Manual');
  expect(screen.queryByRole('button', { name: /Reintentar/ })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Elegir otro cargo' })).toBeVisible();
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveAccessibleDescription(/Este cargo requiere revisión del Manual/);
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
async function seleccionarConError(code = 'MANUAL_FICHA_AMBIGUA', status = 422) {
  vi.mocked(dependenciasService.resolver).mockRejectedValueOnce(apiError(code, status));
  setup();
  fireEvent.change(screen.getByLabelText('Fecha de vinculación'), { target: { value: '2024-01-15' } });
  await userEvent.type(screen.getByLabelText('Número de cédula'), '12345678');
  await userEvent.type(screen.getByLabelText('Nombres'), 'Persona');
  await userEvent.type(screen.getByLabelText('Apellidos'), 'Sintética');
  await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Profesional A/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  await screen.findByRole('alert');
}
it('elegir otro cargo limpia perfil y cargo, conserva dependencia y datos personales, y devuelve foco', async () => {
  await seleccionarConError();
  await userEvent.click(screen.getByRole('button', { name: 'Elegir otro cargo' }));
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue(a);
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveFocus();
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  expect(screen.queryByText(/Perfil funcional asociado:/)).not.toBeInTheDocument();
  expect(screen.getByLabelText('Nombres')).toHaveValue('Persona');
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  await screen.findByText(/TEST-A/);
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeEnabled();
});
it('cambiar dependencia limpia toda selección derivada y devuelve foco sin borrar datos personales', async () => {
  await seleccionarConError();
  await userEvent.click(screen.getByRole('button', { name: 'Cambiar dependencia' }));
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue('');
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveFocus();
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toBeDisabled();
  expect(screen.getByLabelText('Buscar cargo por nombre, código, grado o página')).toHaveValue('');
  expect(screen.getByLabelText('Buscar dependencia por nombre')).toHaveValue('');
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  expect(screen.queryByText(/Perfil funcional asociado:/)).not.toBeInTheDocument();
  expect(screen.getByLabelText('Nombres')).toHaveValue('Persona');
});
it('submit directo también queda bloqueado con ambigüedad y datos válidos', async () => {
  await seleccionarConError();
  fireEvent.submit(screen.getByRole('button', { name: 'Guardar funcionario' }).closest('form')!);
  await act(async () => {});
  expect(funcionariosService.create).not.toHaveBeenCalled();
});
it.each([502, 503, 408, 429])('fallo técnico HTTP %s ofrece retry y vuelve a consultar el mismo perfil', async (status) => {
  await seleccionarConError('', status);
  expect(screen.getByRole('alert')).toHaveTextContent('No pudimos consultar el Manual');
  await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
  await screen.findByText(/TEST-A/);
  expect(dependenciasService.resolver).toHaveBeenCalledTimes(2);
  expect(dependenciasService.resolver).toHaveBeenLastCalledWith(a, '41', '2024-01-15');
});
it('fallo de conexión sin respuesta ofrece retry', async () => {
  vi.mocked(dependenciasService.resolver).mockRejectedValueOnce({ isAxiosError: true, code: 'ERR_NETWORK' });
  setup();
  await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Profesional A/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), '41');
  expect(await screen.findByRole('alert')).toHaveTextContent('Revise la conexión o inténtelo nuevamente.');
  await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
  await screen.findByText(/TEST-A/);
  expect(dependenciasService.resolver).toHaveBeenCalledTimes(2);
});
it.each(['MANUAL_FICHA_NO_ENCONTRADA', 'MANUAL_FICHA_INCOMPLETA', 'MANUAL_FICHA_INCOMPATIBLE', 'MANUAL_VERSION_NO_VIGENTE'])('%s no ofrece retry', async (code) => {
  await seleccionarConError(code);
  expect(screen.queryByRole('button', { name: /Reintentar/ })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Elegir otro cargo' })).toBeVisible();
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
  if (code === 'MANUAL_FICHA_NO_ENCONTRADA') {
    expect(screen.getByRole('alert')).toHaveTextContent('No se encontró un perfil del Manual compatible con este cargo, dependencia y fecha.');
    expect(screen.queryByText('Este cargo requiere revisión del Manual')).not.toBeInTheDocument();
  }
});
it('validación de cargo requerida no ofrece reintentar la carga', async () => {
  setup(); await seleccionar();
  fireEvent.change(screen.getByRole('combobox', { name: 'Cargo' }), { target: { value: '' } });
  await screen.findByText('Seleccione un cargo.');
  expect(screen.queryByRole('button', { name: /Reintentar/ })).not.toBeInTheDocument();
});
it.each([true, false])('edición preserva ficha histórica sin resolverla nuevamente (legacy=%s)', async (legacy) => {
  vi.mocked(dependenciasService.cargos).mockResolvedValue([{ ...cargo, perfiles_count: 2 }]);
  vi.mocked(dependenciasService.resolver).mockRejectedValue(apiError('MANUAL_FICHA_AMBIGUA'));
  vi.mocked(funcionariosService.getById).mockResolvedValue({ success: true, data: { id: 7, tipo_documento: 'CC', numero_documento: '12345678', nombres: 'Persona', apellidos: 'Prueba', estado: 'activo', fecha_ingreso: '2024-01-15', fecha_seleccion: '2026-10-05', unidad_organizacional: { id: a, nombre: 'Unidad A' }, vinculacion_legacy: legacy, cargo: { ...cargo, nivel: 'Profesional', estado: true }, asignacion_actual: { id: 1, manual_cargo_version_id: 99, ficha_manual: { id: 99, source_id: 'LEGACY-99', area_funcional: 'Unidad A', version: 'Anterior' }, tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa' } } });
  vi.mocked(funcionariosService.update).mockResolvedValue({ success: true, data: { id: 7 } } as never);
  setup(true); await screen.findByText(/LEGACY-99/);
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue(a);
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('ficha-99');
  expect(dependenciasService.resolver).not.toHaveBeenCalled();
  expect(screen.queryByText('Este cargo requiere revisión del Manual')).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeEnabled();
  await screen.findByRole('option', { name: /Requiere revisión del Manual/ });
  await userEvent.click(screen.getByRole('button', { name: 'Guardar funcionario' }));
  await waitFor(() => expect(funcionariosService.update).toHaveBeenCalledWith('7', expect.objectContaining({ unidad_organizacional_id: a, cargo_id: '41' }), true));
});
it('ambigüedad detectada al guardar también bloquea y se presenta con acciones de dominio', async () => {
  vi.mocked(funcionariosService.create).mockRejectedValueOnce(apiError('MANUAL_FICHA_AMBIGUA'));
  setup();
  fireEvent.change(screen.getByLabelText('Fecha de vinculación'), { target: { value: '2024-01-15' } });
  await seleccionar();
  await userEvent.type(screen.getByLabelText('Número de cédula'), '12345678');
  await userEvent.type(screen.getByLabelText('Nombres'), 'Persona');
  await userEvent.type(screen.getByLabelText('Apellidos'), 'Sintética');
  await userEvent.click(screen.getByRole('button', { name: 'Guardar funcionario' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Este cargo requiere revisión del Manual');
  expect(screen.queryByRole('button', { name: /Reintentar/ })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
  await userEvent.click(screen.getByRole('button', { name: 'Elegir otro cargo' }));
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  expect(screen.getByRole('combobox', { name: 'Cargo' })).toHaveValue('');
  expect(screen.getByRole('combobox', { name: 'Dependencia' })).toHaveValue(a);
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
  expect(values.manual_cargo_version_id).toBe('');
  expect(values).not.toHaveProperty('dependencia');
});

it('distingue perfiles por página y consulta solo las funciones de la ficha elegida', async () => {
  const first = { ...cargo, manual_cargo_version_id: 101, source_id: 'TEST-101', pagina_inicio: 328, pagina_verificada: true, seleccionable: true };
  const second = { ...first, manual_cargo_version_id: 102, source_id: 'TEST-102', pagina_inicio: 412 };
  vi.mocked(dependenciasService.cargos).mockResolvedValue([first, second]);
  vi.mocked(dependenciasService.resolver).mockImplementation(async (_unit, _cargo, _fecha, ficha) => ({ source_id: `TEST-${ficha}`, funciones_count: 1, pagina_inicio: ficha === '101' ? 328 : 412 }));
  vi.mocked(dependenciasService.funciones).mockImplementation(async (_unit, _cargo, _fecha, ficha) => ({ source_id: `TEST-${ficha}`, funciones_count: 1, pagina_inicio: ficha === '101' ? 328 : 412, pagina_verificada: true, version: 'Manual sintético', funciones: [{ orden: 1, descripcion: `Función exacta ${ficha}`, numero_fuente: 1, grupo: '' }] }));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: 'Profesional A · 219/02 · Página 328' });
  expect(screen.getByRole('option', { name: 'Profesional A · 219/02 · Página 412' })).toBeVisible();
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), 'ficha-101');
  await screen.findByText(/TEST-101/);
  expect(dependenciasService.funciones).not.toHaveBeenCalled();
  await userEvent.click(screen.getByRole('button', { name: 'Ver funciones del cargo' }));
  expect(await screen.findByText('Función exacta 101')).toBeVisible();
  expect(screen.queryByRole('textbox', { name: /funciones/i })).not.toBeInTheDocument();
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), 'ficha-102');
  expect(screen.queryByText('Función exacta 101')).not.toBeInTheDocument();
  await screen.findByText(/TEST-102/);
  await userEvent.click(screen.getByRole('button', { name: 'Ver funciones del cargo' }));
  expect(await screen.findByText('Función exacta 102')).toBeVisible();
  expect(dependenciasService.resolver).toHaveBeenLastCalledWith(a, '41', expect.any(String), '102');
});

it('respuesta tardía de funciones no restaura la vista al cambiar dependencia', async () => {
  vi.mocked(dependenciasService.cargos).mockResolvedValue([{ ...cargo, manual_cargo_version_id: 101, pagina_inicio: 328, pagina_verificada: true }]);
  let complete!: (value: never) => void;
  vi.mocked(dependenciasService.funciones).mockReturnValue(new Promise((resolve) => { complete = resolve; }));
  setup(); await screen.findByRole('option', { name: 'Unidad A' });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), a);
  await screen.findByRole('option', { name: /Página 328/ });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Cargo' }), 'ficha-101');
  await screen.findByText(/TEST-A/);
  await userEvent.click(screen.getByRole('button', { name: 'Ver funciones del cargo' }));
  await screen.findByText('Cargando funciones...');
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Dependencia' }), b);
  await act(async () => complete({ source_id: 'TEST-101', funciones_count: 1, funciones: [{ orden: 1, descripcion: 'Función tardía', numero_fuente: 1, grupo: '' }] } as never));
  expect(screen.queryByText('Función tardía')).not.toBeInTheDocument();
  expect(screen.queryByRole('region', { name: 'Funciones del cargo' })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Guardar funcionario' })).toBeDisabled();
});
