import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse } from '../../../lib/api/mockAdapter';
import { mockPagos } from '../../../lib/mocks/mockPagos';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { PagoSoporte } from '../types/pago.types';

export const pagosService = {
  async list(): Promise<ApiResponse<PaginatedResponse<PagoSoporte>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockPagos);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<PagoSoporte>>>(endpoints.pagos);
    return response.data;
  },
};
