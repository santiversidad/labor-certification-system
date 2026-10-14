import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import { SalaryScalePage } from '../pages/SalaryScalePage';
import { salaryScaleService } from '../services/salary-scale.service';
import { cop, percentageSchema } from '../schemas/salary-scale.schema';
import type { SalaryTable, SalaryYear } from '../types/salary-scale.types';

const auth = vi.hoisted(() => ({ roles: ['admin'] }));
vi.mock('../../auth/hooks/useAuth', () => ({ useAuth: () => ({ user: { roles: auth.roles, tipo_cuenta: auth.roles[0] === 'funcionario' ? 'funcionario' : 'backoffice' } }) }));
vi.mock('../services/salary-scale.service', () => ({ salaryScaleService: { list: vi.fn(), create: vi.fn(), preview: vi.fn(), calculate: vi.fn(), edit: vi.fn(), metadata: vi.fn(), publish: vi.fn() } }));
const year = (id: number, anio: number, estado: SalaryYear['estado']): SalaryYear => ({ id, anio, estado, revision: 3, porcentaje_referencia: null, vigencia_base_id: null, fuente: 'Fuente sintética', acto_administrativo: null, fecha_vigencia: null, observaciones: null });
const table = (): SalaryTable => ({ vigencias: [year(1, 2024, 'archivada'), year(2, 2025, 'vigente')], filas: [{ codigo: '099', grado: '02', denominacion: 'Profesional sintético', valores: [1, 2].map((id) => ({ id, vigencia_id: id, cargo_id: 4, codigo: '099', grado: '02', denominacion: 'Profesional sintético', numero_cargos: id + 1, asignacion_basica_mensual: '1000001', revision: 1, origen_valor: 'importado' })) }], meta: { current_page: 1, last_page: 1, total: 1 } });
beforeEach(() => {
  vi.resetAllMocks(); auth.roles = ['admin'];
  vi.mocked(salaryScaleService.list).mockResolvedValue(table());
  vi.mocked(salaryScaleService.preview).mockResolvedValue({ cantidad: 1, base_revision: 5, ejemplos: [{ codigo: '099', grado: '02', denominacion: 'Profesional sintético', valor_base: '1000001', valor_nuevo: '1070001' }] });
  vi.mocked(salaryScaleService.create).mockResolvedValue(year(3, 2026, 'borrador'));
});
function setup() { const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } }); render(<QueryClientProvider client={client}><SalaryScalePage /></QueryClientProvider>); }
async function createDialog() { setup(); await screen.findByText('Profesional sintético'); await userEvent.click(screen.getByRole('button', { name: 'Crear vigencia' })); fireEvent.change(screen.getByLabelText('Año nuevo'), { target: { value: '2026' } }); await userEvent.type(screen.getByLabelText('Fuente / procedencia'), 'Ejercicio sintético'); }

it('genera columnas dinámicas, conserva ceros y muestra número de cargos por año', async () => {
  const data = table(); data.vigencias.push(year(3, 2027, 'borrador')); vi.mocked(salaryScaleService.list).mockResolvedValue(data);
  setup(); await screen.findByText('Profesional sintético');
  for (const value of ['2024', '2025', '2027', '099', '02', 'N.º cargos: 2', 'N.º cargos: 3']) expect(screen.getByText(value, { exact: true })).toBeVisible();
  expect(screen.getByText(`${new Date().getFullYear()} · Pendiente de registrar`)).toBeVisible();
});
it('busca en servidor por código, grado o denominación', async () => { setup(); await screen.findByText('Profesional sintético'); await userEvent.type(screen.getByLabelText('Buscar por código, grado o denominación'), '099'); await userEvent.click(screen.getByRole('button', { name: 'Buscar' })); await waitFor(() => expect(salaryScaleService.list).toHaveBeenLastCalledWith('099', 1)); });
it('conserva filas sin vinculación al catálogo y permite consultar sus salarios', async () => {
  const data = table();
  data.filas[0].valores.forEach((item) => { item.cargo_id = null; });
  vi.mocked(salaryScaleService.list).mockResolvedValue(data);
  setup();
  const row = (await screen.findByText('Profesional sintético')).closest('tr')!;
  expect(within(row).getAllByText('Sin vinculación al catálogo')).toHaveLength(2);
  expect(within(row).getAllByText(/\$\s+1\.000\.001/)).toHaveLength(2);
  expect(within(row).getByText('N.º cargos: 2')).toBeVisible();
  expect(within(row).getByText('N.º cargos: 3')).toBeVisible();
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument();
});
it('crea desde base sin porcentaje con valores pendientes y no ejecuta cálculo', async () => { await createDialog(); await userEvent.click(screen.getByRole('button', { name: 'Crear borrador' })); await waitFor(() => expect(salaryScaleService.create).toHaveBeenCalledWith({ anio: 2026, fuente: 'Ejercicio sintético', vigencia_base_id: 2, base_revision: 3 })); expect(salaryScaleService.preview).not.toHaveBeenCalled(); expect(await screen.findByText(/Cambio guardado/)).toBeVisible(); });
it('previsualiza con el servidor y exige confirmar antes de generar', async () => {
  await createDialog(); await userEvent.type(screen.getByLabelText('Porcentaje de aumento (%) · opcional'), '7,00');
  await userEvent.click(screen.getByRole('button', { name: 'Previsualizar aumento' }));
  expect(await screen.findByText('Vista previa · 1 filas')).toBeVisible(); expect(screen.getByText(/1\.000\.001.*→.*1\.070\.001/)).toBeVisible();
  expect(salaryScaleService.create).not.toHaveBeenCalled();
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar generación del borrador' }));
  await waitFor(() => expect(salaryScaleService.create).toHaveBeenCalledWith({ anio: 2026, fuente: 'Ejercicio sintético', vigencia_base_id: 2, base_revision: 5, porcentaje: '7,00', confirmado: true }));
});
it('cambiar porcentaje invalida la vista previa previa', async () => { await createDialog(); await userEvent.type(screen.getByLabelText('Porcentaje de aumento (%) · opcional'), '7'); await userEvent.click(screen.getByRole('button', { name: 'Previsualizar aumento' })); await screen.findByText('Vista previa · 1 filas'); await userEvent.type(screen.getByLabelText('Porcentaje de aumento (%) · opcional'), ',5'); expect(screen.queryByText('Vista previa · 1 filas')).not.toBeInTheDocument(); expect(screen.getByRole('button', { name: 'Previsualizar aumento' })).toBeEnabled(); });
it('impide porcentajes absurdos y admite captura decimal con coma', () => { for (const value of ['-1', 'NaN', 'Infinity', '101', '7.123']) expect(percentageSchema.safeParse(value).success).toBe(false); expect(percentageSchema.safeParse('7,50').success).toBe(true); });
it('edita asignación y cantidad solo en el borrador', async () => {
  const data = table(); data.vigencias[1].estado = 'borrador'; vi.mocked(salaryScaleService.list).mockResolvedValue(data);
  setup(); const button = await screen.findByRole('button', { name: 'Editar 099 grado 02 2025' }); expect(screen.queryByRole('button', { name: 'Editar 099 grado 02 2024' })).not.toBeInTheDocument();
  await userEvent.click(button); fireEvent.change(screen.getByLabelText('Asignación básica mensual (COP)'), { target: { value: '1200000' } }); fireEvent.change(screen.getByLabelText('Número de cargos en esta vigencia'), { target: { value: '7' } }); await userEvent.click(screen.getByRole('button', { name: 'Guardar valor' }));
  await waitFor(() => expect(salaryScaleService.edit).toHaveBeenCalledWith(data.filas[0].valores[1], '1200000', 7));
});
it('aplica aumento a borrador existente tras preview y confirmación', async () => {
  const data = table(); const target = year(3, 2026, 'borrador'); target.vigencia_base_id = 2; data.vigencias.push(target); vi.mocked(salaryScaleService.list).mockResolvedValue(data);
  setup(); await userEvent.click(await screen.findByRole('button', { name: 'Aplicar aumento 2026' })); await userEvent.type(screen.getByLabelText('Porcentaje de aumento (%)'), '7'); await userEvent.click(screen.getByRole('button', { name: 'Previsualizar aumento' })); await userEvent.click(await screen.findByRole('button', { name: 'Confirmar generación del borrador' }));
  await waitFor(() => expect(salaryScaleService.calculate).toHaveBeenCalledWith(target, { vigencia_base_id: 2, base_revision: 5, porcentaje: '7', confirmado: true }));
});
it('espera la tabla actualizada después de editar antes de permitir publicación', async () => {
  const before = table(); before.vigencias[1].estado = 'borrador';
  const after = table(); after.vigencias[1] = { ...before.vigencias[1], revision: 4 };
  let resolveReload!: (data: SalaryTable) => void;
  vi.mocked(salaryScaleService.list).mockResolvedValueOnce(before).mockImplementationOnce(() => new Promise((resolve) => { resolveReload = resolve; }));
  setup(); await userEvent.click(await screen.findByRole('button', { name: 'Editar 099 grado 02 2025' }));
  await userEvent.click(screen.getByRole('button', { name: 'Guardar valor' }));
  await waitFor(() => expect(salaryScaleService.list).toHaveBeenCalledTimes(2));
  expect(screen.getByRole('dialog')).toBeVisible(); expect(screen.getByRole('button', { name: 'Guardar valor' })).toBeDisabled();
  resolveReload(after); await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  await userEvent.click(screen.getByRole('button', { name: 'Publicar 2025' })); await userEvent.click(screen.getByRole('button', { name: 'Confirmar publicación' }));
  await waitFor(() => expect(salaryScaleService.publish).toHaveBeenCalledWith(expect.objectContaining({ revision: 4 })));
});
it('publicación explica procedencia pendiente y exige confirmación', async () => { const data = table(); data.vigencias[1].estado = 'borrador'; vi.mocked(salaryScaleService.list).mockResolvedValue(data); setup(); await userEvent.click(await screen.findByRole('button', { name: 'Publicar 2025' })); expect(screen.getByText(/Publicar en el sistema no acredita un decreto/)).toBeVisible(); expect(salaryScaleService.publish).not.toHaveBeenCalled(); await userEvent.click(screen.getByRole('button', { name: 'Confirmar publicación' })); await waitFor(() => expect(salaryScaleService.publish).toHaveBeenCalledWith(data.vigencias[1])); });
it('permite registrar acto después de publicar sin editar valores históricos', async () => { setup(); await userEvent.click(await screen.findByRole('button', { name: 'Procedencia 2025' })); await userEvent.type(screen.getByLabelText('Acto administrativo · opcional'), 'Acto sintético'); await userEvent.click(screen.getByRole('button', { name: 'Guardar procedencia' })); await waitFor(() => expect(salaryScaleService.metadata).toHaveBeenCalledWith(table().vigencias[1], expect.objectContaining({ acto_administrativo: 'Acto sintético' }))); expect(salaryScaleService.edit).not.toHaveBeenCalled(); });
it('muestra rechazo del servidor al publicar borrador incompleto', async () => { const data = table(); data.vigencias[1].estado = 'borrador'; vi.mocked(salaryScaleService.list).mockResolvedValue(data); vi.mocked(salaryScaleService.publish).mockRejectedValue({ response: { data: { errors: { vigencia: ['Complete todas las asignaciones básicas antes de publicar.'] } } } }); setup(); await userEvent.click(await screen.findByRole('button', { name: 'Publicar 2025' })); await userEvent.click(screen.getByRole('button', { name: 'Confirmar publicación' })); expect(await screen.findByRole('alert')).toHaveTextContent('Complete todas las asignaciones'); });
it('cargando no permite crear hasta conocer la tabla', () => { vi.mocked(salaryScaleService.list).mockReturnValue(new Promise(() => {})); setup(); expect(screen.getByRole('status')).toHaveTextContent('Consultando'); expect(screen.getByRole('button', { name: 'Crear vigencia' })).toBeDisabled(); });
it('error técnico permite reintentar', async () => { vi.mocked(salaryScaleService.list).mockRejectedValueOnce(new Error('Conexión temporalmente indisponible')); setup(); await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' })); expect(await screen.findByText('Profesional sintético')).toBeVisible(); expect(salaryScaleService.list).toHaveBeenCalledTimes(2); });
it('tabla vacía explica la fuente pendiente sin cifras inventadas', async () => { vi.mocked(salaryScaleService.list).mockResolvedValue({ vigencias: [], filas: [], meta: { current_page: 1, last_page: 1, total: 0 } }); setup(); expect(await screen.findByText(/No hay vigencias registradas/)).toBeVisible(); expect(screen.queryByRole('table')).not.toBeInTheDocument(); });
it.each(['secretario', 'funcionario'])('oculta módulo y no consulta API para %s', (role) => { auth.roles = [role]; setup(); expect(screen.getByRole('alert')).toHaveTextContent('únicamente para administradores'); expect(salaryScaleService.list).not.toHaveBeenCalled(); expect(screen.queryByRole('button', { name: 'Crear vigencia' })).not.toBeInTheDocument(); });
it('formatea COP sin decimales y mantiene overflow dentro de región accesible', async () => { expect(cop('6134334')).toMatch(/6\.134\.334/); expect(cop(null)).toBe('Pendiente'); setup(); await screen.findByText('Profesional sintético'); const region = screen.getByRole('region', { name: /Tabla de escala salarial/ }); expect(region).toHaveClass('overflow-x-auto'); expect(region).toHaveAttribute('tabindex', '0'); expect(within(region).getByRole('table')).toHaveClass('min-w-[780px]'); });
