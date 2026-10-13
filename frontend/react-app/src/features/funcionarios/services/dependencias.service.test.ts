import { beforeEach, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { dependenciasService } from './dependencias.service';

vi.mock('../../../lib/api/apiClient', () => ({ apiClient: { get: vi.fn() } }));
beforeEach(() => vi.clearAllMocks());
it('recupera todas las páginas resumidas conservando dependencia, fecha y búsqueda', async () => {
  vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [{ manual_cargo_version_id: 1, pagina_inicio: 101 }], meta: { last_page: 2 } } }).mockResolvedValueOnce({ data: { data: [{ manual_cargo_version_id: 2, pagina_inicio: 102 }], meta: { last_page: 2 } } });
  expect(await dependenciasService.cargos('unidad-a', '2024-01-15', '102')).toEqual([{ manual_cargo_version_id: 1, pagina_inicio: 101 }, { manual_cargo_version_id: 2, pagina_inicio: 102 }]);
  expect(apiClient.get).toHaveBeenNthCalledWith(2, '/manual-funciones/dependencias/cargos', { params: { unidad_organizacional_id: 'unidad-a', fecha: '2024-01-15', q: '102', per_page: 100, perfiles: 1, page: 2 } });
});
it('la vista previa solicita únicamente la ficha explícita y no envía páginas editables', async () => {
  vi.mocked(apiClient.get).mockResolvedValue({ data: { data: { source_id: 'MF-0002', funciones: [] } } });
  await dependenciasService.funciones('unidad-a', '12', '2024-01-15', '34');
  expect(apiClient.get).toHaveBeenCalledWith('/manual-funciones/dependencias/resolver', { params: { unidad_organizacional_id: 'unidad-a', cargo_id: '12', fecha: '2024-01-15', manual_cargo_version_id: '34', funciones: 1 } });
});
