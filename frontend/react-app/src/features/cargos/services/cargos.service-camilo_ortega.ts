import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Cargo } from '../types/cargo.types';
import type { CargoFormValues } from '../schemas/cargo.schema';

export const cargosService = {
  async list(): Promise<ApiResponse<Cargo[]>> {
    const response = await apiClient.get<ApiResponse<Cargo[]>>(endpoints.cargos);
    return response.data;
  },

  async create(payload: CargoFormValues): Promise<ApiResponse<Cargo>> {
    const response = await apiClient.post<ApiResponse<Cargo>>(endpoints.cargos, payload);
    return response.data;
  },
};
