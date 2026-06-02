import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse } from '../../../lib/api/mockAdapter';
import { mockAuditoria } from '../../../lib/mocks/mockAuditoria';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { AuditLog } from '../types/auditoria.types';

export const auditoriaService = {
  async list(): Promise<ApiResponse<PaginatedResponse<AuditLog>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockAuditoria);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<AuditLog>>>(endpoints.auditoria);
    return response.data;
  },
};
