import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse } from '../../../lib/api/mockAdapter';
import { mockCargos } from '../../../lib/mocks/mockCargos';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { Cargo } from '../types/cargo.types';

export const cargosService = {
  async list(): Promise<ApiResponse<PaginatedResponse<Cargo>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockCargos);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<Cargo>>>(endpoints.cargos);
    return response.data;
  },
};
