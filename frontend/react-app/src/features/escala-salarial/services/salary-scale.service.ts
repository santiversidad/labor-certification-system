import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { IncreasePreview, SalaryItem, SalaryTable, SalaryYear, YearPayload } from '../types/salary-scale.types';

const root = '/escala-salarial';
const unwrap = <T>(response: { data: ApiResponse<T> }) => response.data.data;
export const salaryScaleService = {
  async list(q: string, page: number): Promise<SalaryTable> { return unwrap(await apiClient.get(root, { params: { q, page } })); },
  async create(payload: YearPayload): Promise<SalaryYear> { return unwrap(await apiClient.post(`${root}/vigencias`, payload)); },
  async preview(payload: { anio: number; vigencia_base_id: number; porcentaje: string }): Promise<IncreasePreview> {
    return unwrap(await apiClient.post(`${root}/vigencias/previsualizar`, payload));
  },
  async calculate(year: SalaryYear, payload: Omit<YearPayload, 'anio' | 'fuente'>): Promise<SalaryYear> {
    return unwrap(await apiClient.post(`${root}/vigencias/${year.id}/calcular`, { ...payload, revision: year.revision }));
  },
  async edit(item: SalaryItem, salary: string | null, count: number | null): Promise<SalaryItem> {
    return unwrap(await apiClient.patch(`${root}/items/${item.id}`, { revision: item.revision, asignacion_basica_mensual: salary, numero_cargos: count }));
  },
  async metadata(year: SalaryYear, payload: Pick<SalaryYear, 'fuente' | 'acto_administrativo' | 'fecha_vigencia' | 'observaciones'>): Promise<SalaryYear> {
    return unwrap(await apiClient.patch(`${root}/vigencias/${year.id}`, { ...payload, revision: year.revision }));
  },
  async publish(year: SalaryYear): Promise<SalaryYear> {
    return unwrap(await apiClient.post(`${root}/vigencias/${year.id}/publicar`, { revision: year.revision, confirmado: true }));
  },
};
