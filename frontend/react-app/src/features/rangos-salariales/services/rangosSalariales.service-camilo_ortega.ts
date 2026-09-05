import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { RangoSalarial } from '../types/rangoSalarial.types';
import type { RangoSalarialFormValues } from '../schemas/rangoSalarial.schema';

export const rangosSalarialesService = {
  async list(): Promise<ApiResponse<RangoSalarial[]>> {
    const response = await apiClient.get<ApiResponse<RangoSalarial[]>>(endpoints.rangosSalariales);
    return response.data;
  },

  async consultar(codigo: string, grado: string, vigencia: number): Promise<ApiResponse<RangoSalarial>> {
    const response = await apiClient.get<ApiResponse<RangoSalarial>>(
      `${endpoints.rangosSalariales}/consultar`,
      { params: { codigo, grado, vigencia } },
    );
    return response.data;
  },

  async create(payload: RangoSalarialFormValues): Promise<ApiResponse<RangoSalarial>> {
    const response = await apiClient.post<ApiResponse<RangoSalarial>>(endpoints.rangosSalariales, payload);
    return response.data;
  },
};
