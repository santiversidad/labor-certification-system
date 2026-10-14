import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Cargo } from '../types/cargo.types';
import type { CargoFormValues } from '../schemas/cargo.schema';

export const cargosService = {
  async options(): Promise<ApiResponse<Cargo[]>> {
    const first = (await apiClient.get<ApiResponse<Cargo[]>>(endpoints.cargos, { params: { per_page: 100, page: 1 } })).data;
    const items = [...first.data];
    let page = 2;
    while (items.length % 100 === 0 && items.length > 0) {
      const next = (await apiClient.get<ApiResponse<Cargo[]>>(endpoints.cargos, { params: { per_page: 100, page } })).data;
      items.push(...next.data);
      if (next.data.length < 100) break;
      page++;
    }
    return { ...first, data: items };
  },
  async list(): Promise<ApiResponse<Cargo[]>> {
    const response = await apiClient.get<ApiResponse<Cargo[]>>(endpoints.cargos);
    return response.data;
  },

  async create(payload: CargoFormValues): Promise<ApiResponse<Cargo>> {
    const response = await apiClient.post<ApiResponse<Cargo>>(endpoints.cargos, payload);
    return response.data;
  },
};
