import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse, mockResponse } from '../../../lib/api/mockAdapter';
import { mockFuncionarios } from '../../../lib/mocks/mockFuncionarios';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { Funcionario } from '../types/funcionario.types';

export const funcionariosService = {
  async list(): Promise<ApiResponse<PaginatedResponse<Funcionario>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockFuncionarios);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<Funcionario>>>(endpoints.funcionarios);
    return response.data;
  },

  async getById(id: string): Promise<ApiResponse<Funcionario>> {
    if (env.useMocks) {
      const funcionario = mockFuncionarios.find((item) => item.id === id) ?? mockFuncionarios[0];
      return mockResponse(funcionario);
    }

    const response = await apiClient.get<ApiResponse<Funcionario>>(`${endpoints.funcionarios}/${id}`);
    return response.data;
  },
};
