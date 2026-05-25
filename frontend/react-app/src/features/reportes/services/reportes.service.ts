import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockResponse } from '../../../lib/api/mockAdapter';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ReporteResumen } from '../types/reporte.types';

export const reportesService = {
  async getSummary(): Promise<ApiResponse<ReporteResumen>> {
    if (env.useMocks) {
      return mockResponse({
        totalSolicitudes: 48,
        solicitudesAprobadas: 32,
        certificadosGenerados: 29,
        pagosPendientes: 6,
      });
    }

    const response = await apiClient.get<ApiResponse<ReporteResumen>>(endpoints.reportes);
    return response.data;
  },
};
