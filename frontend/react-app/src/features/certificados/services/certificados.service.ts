import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Certificado } from '../types/certificado.types';

export const certificadosService = {
  async list(): Promise<ApiResponse<Certificado[]>> {
    const response = await apiClient.get<ApiResponse<Certificado[]>>(endpoints.certificados);
    return response.data;
  },

  async getById(id: string | number): Promise<ApiResponse<Certificado>> {
    const response = await apiClient.get<ApiResponse<Certificado>>(
      `${endpoints.certificados}/${id}`,
    );
    return response.data;
  },
};
