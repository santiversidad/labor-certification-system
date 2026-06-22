import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Funcionario } from '../types/funcionario.types';

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
};
