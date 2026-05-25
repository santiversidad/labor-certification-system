import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockResponse } from '../../../lib/api/mockAdapter';
import { mockSolicitudes } from '../../../lib/mocks/mockSolicitudes';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { DashboardSummary } from '../types/dashboard.types';

export const dashboardService = {
  async getSummary(): Promise<ApiResponse<DashboardSummary>> {
    if (env.useMocks) {
      return mockResponse({
        metrics: [
          { label: 'Solicitudes pendientes', value: 8, tone: 'gold' },
          { label: 'En revision', value: 5, tone: 'blue' },
          { label: 'Certificados generados', value: 21, tone: 'green' },
          { label: 'Rechazadas', value: 2, tone: 'red' },
        ],
        recentRequests: mockSolicitudes,
      });
    }

    const response = await apiClient.get<ApiResponse<DashboardSummary>>(endpoints.dashboard);
    return response.data;
  },
};
