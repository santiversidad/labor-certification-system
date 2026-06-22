import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse, mockResponse } from '../../../lib/api/mockAdapter';
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

  async approve(id: string): Promise<ApiResponse<PagoSoporte>> {
    if (env.useMocks) {
      const pago = mockPagos.find((item) => item.id === id) ?? mockPagos[0];
      return mockResponse({ ...pago, estado: 'aprobado' }, 'Pago aprobado.');
    }

    const response = await apiClient.post<ApiResponse<PagoSoporte>>(endpoints.pagosActions.aprobar(id));
    return response.data;
  },

  async reject(id: string, observacion: string): Promise<ApiResponse<PagoSoporte>> {
    if (env.useMocks) {
      const pago = mockPagos.find((item) => item.id === id) ?? mockPagos[0];
      return mockResponse({ ...pago, estado: 'rechazado', observacion }, 'Pago rechazado.');
    }

    const response = await apiClient.post<ApiResponse<PagoSoporte>>(endpoints.pagosActions.rechazar(id), { observacion });
    return response.data;
  },
};
