import { beforeEach, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { funcionariosService } from './funcionarios.service';
import type { FuncionarioFormValues } from '../schemas/funcionario.schema';

vi.mock('../../../lib/api/apiClient', () => ({ apiClient: { post: vi.fn(), put: vi.fn() } }));
const values: FuncionarioFormValues = { tipo_documento: 'CC', numero_documento: '12345678', nombres: 'Persona', apellidos: 'Sintética', fecha_ingreso: '2024-01-15', unidad_organizacional_id: 'a'.repeat(32), cargo_id: '41', tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa' };
beforeEach(() => { vi.clearAllMocks(); vi.mocked(apiClient.post).mockResolvedValue({ data: {} }); vi.mocked(apiClient.put).mockResolvedValue({ data: {} }); });
it('creación envía ID de unidad y cargo numérico sin identificadores de ficha', async () => {
  await funcionariosService.create(values);
  const sent = vi.mocked(apiClient.post).mock.calls[0][1];
  expect(sent).toMatchObject({ unidad_organizacional_id: 'a'.repeat(32), cargo_id: 41, fecha_retiro: null });
  expect(sent).not.toHaveProperty('manual_cargo_version_id');
});
it('edición sin cambio laboral omite unidad y cargo; cambio explícito envía ambos IDs', async () => {
  await funcionariosService.update(7, values, true);
  expect(vi.mocked(apiClient.put).mock.calls[0][1]).not.toHaveProperty('cargo_id');
  expect(vi.mocked(apiClient.put).mock.calls[0][1]).not.toHaveProperty('unidad_organizacional_id');
  await funcionariosService.update(7, values);
  expect(vi.mocked(apiClient.put).mock.calls[1][1]).toMatchObject({ unidad_organizacional_id: 'a'.repeat(32), cargo_id: 41 });
});
it('envía la ficha como ID numérico y la omite al conservar vinculación histórica', async () => {
  await funcionariosService.create({ ...values, manual_cargo_version_id: '102' });
  expect(vi.mocked(apiClient.post).mock.calls[0][1]).toMatchObject({ cargo_id: 41, manual_cargo_version_id: 102 });
  await funcionariosService.update(7, { ...values, manual_cargo_version_id: '102' }, true);
  expect(vi.mocked(apiClient.put).mock.calls[0][1]).not.toHaveProperty('manual_cargo_version_id');
});
