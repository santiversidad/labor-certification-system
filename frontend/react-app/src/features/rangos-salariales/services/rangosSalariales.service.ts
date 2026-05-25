import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse } from '../../../lib/api/mockAdapter';
import { mockRangosSalariales } from '../../../lib/mocks/mockRangosSalariales';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { RangoSalarial } from '../types/rangoSalarial.types';

export const rangosSalarialesService = {
  async list(): Promise<ApiResponse<PaginatedResponse<RangoSalarial>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockRangosSalariales);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<RangoSalarial>>>(endpoints.rangosSalariales);
    return response.data;
  },
};
