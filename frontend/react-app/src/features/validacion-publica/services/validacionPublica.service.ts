import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export const validacionPublicaService = {
  async validate(token: string): Promise<ApiResponse<ValidacionCertificado>> {
    const response = await apiClient.get<ApiResponse<ValidacionCertificado>>(
      endpoints.validacionPublica(token),
    );
    return response.data;
  },
};
