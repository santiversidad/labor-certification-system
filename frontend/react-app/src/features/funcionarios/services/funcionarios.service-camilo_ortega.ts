import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Funcionario } from '../types/funcionario.types';
import type { FuncionarioFormValues } from '../schemas/funcionario.schema';

type FuncionarioPayload = Omit<FuncionarioFormValues, 'user_id' | 'cargo_id'> & {
  user_id?: number | null;
  cargo_id?: number | null;
};

export const funcionariosService = {
  async list(): Promise<ApiResponse<Funcionario[]>> {
    const response = await apiClient.get<ApiResponse<Funcionario[]>>(endpoints.funcionarios);
    return response.data;
  },

  async getById(id: string | number): Promise<ApiResponse<Funcionario>> {
    const response = await apiClient.get<ApiResponse<Funcionario>>(
      `${endpoints.funcionarios}/${id}`,
    );
    return response.data;
  },

  async create(payload: FuncionarioPayload): Promise<ApiResponse<Funcionario>> {
    const response = await apiClient.post<ApiResponse<Funcionario>>(endpoints.funcionarios, payload);
    return response.data;
  },

  async update(id: string | number, payload: FuncionarioPayload): Promise<ApiResponse<Funcionario>> {
    const response = await apiClient.put<ApiResponse<Funcionario>>(`${endpoints.funcionarios}/${id}`, payload);
    return response.data;
  },
};
