import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse, mockResponse } from '../../../lib/api/mockAdapter';
import { mockCertificados } from '../../../lib/mocks/mockCertificados';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { Certificado } from '../types/certificado.types';

export const certificadosService = {
  async list(): Promise<ApiResponse<PaginatedResponse<Certificado>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockCertificados);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<Certificado>>>(endpoints.certificados);
    return response.data;
  },

  async getById(id: string): Promise<ApiResponse<Certificado>> {
    if (env.useMocks) {
      const certificado = mockCertificados.find((item) => item.id === id) ?? mockCertificados[0];
      return mockResponse(certificado);
    }

    const response = await apiClient.get<ApiResponse<Certificado>>(`${endpoints.certificados}/${id}`);
    return response.data;
  },

  async annul(id: string, motivo: string): Promise<ApiResponse<Certificado>> {
    if (env.useMocks) {
      const certificado = mockCertificados.find((item) => item.id === id) ?? mockCertificados[0];
      return mockResponse({ ...certificado, estado: 'anulado' }, motivo);
    }

    const response = await apiClient.post<ApiResponse<Certificado>>(endpoints.certificadosActions.anular(id), { motivo });
    return response.data;
  },
};
