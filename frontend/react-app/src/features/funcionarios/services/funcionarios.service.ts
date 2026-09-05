import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { FuncionarioFormValues } from '../schemas/funcionario.schema';
import type { Funcionario } from '../types/funcionario.types';

export type FuncionarioFilters = { page?: number; per_page?: number; q?: string; cargo_id?: string; dependencia?: string; estado?: string };
const payload = (values: FuncionarioFormValues) => ({ ...values, cargo_id: Number(values.cargo_id), manual_cargo_version_id: Number(values.manual_cargo_version_id), fecha_retiro: values.fecha_retiro || null });

export const funcionariosService = {
  async list(filters: FuncionarioFilters = {}): Promise<ApiResponse<Funcionario[]>> {
    return (await apiClient.get<ApiResponse<Funcionario[]>>(endpoints.funcionarios, { params: filters })).data;
  },
  async getById(id: string | number): Promise<ApiResponse<Funcionario>> {
    return (await apiClient.get<ApiResponse<Funcionario>>(`${endpoints.funcionarios}/${id}`)).data;
  },
  async create(values: FuncionarioFormValues): Promise<ApiResponse<Funcionario>> {
    return (await apiClient.post<ApiResponse<Funcionario>>(endpoints.funcionarios, payload(values))).data;
  },
  async update(id: string | number, values: FuncionarioFormValues): Promise<ApiResponse<Funcionario>> {
    return (await apiClient.put<ApiResponse<Funcionario>>(`${endpoints.funcionarios}/${id}`, payload(values))).data;
  },
  async resetAccess(id: string | number): Promise<void> {
    await apiClient.post(endpoints.resetFuncionarioAccess(id));
  },
};
