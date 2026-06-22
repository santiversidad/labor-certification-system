import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { AuditLog } from '../types/auditoria.types';

export const auditoriaService = {
  async list(): Promise<ApiResponse<AuditLog[]>> {
    const response = await apiClient.get<ApiResponse<AuditLog[]>>(endpoints.auditoria);
    return response.data;
  },
};
