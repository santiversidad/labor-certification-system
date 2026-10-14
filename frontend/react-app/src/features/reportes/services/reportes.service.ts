import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ReporteResumen } from '../types/reporte.types';

export const reportesService = {
  async getSummary(): Promise<ApiResponse<ReporteResumen>> {
    const response = await apiClient.get<ApiResponse<ReporteResumen>>(endpoints.reportes);
    return response.data;
  },
};
