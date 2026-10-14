import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ValidacionCertificado } from '../types/validacionPublica.types';
import axios from 'axios';

export const validacionPublicaService = {
  async validate(token: string): Promise<ApiResponse<ValidacionCertificado>> {
    try {
      const response = await apiClient.get<ApiResponse<ValidacionCertificado>>(
        endpoints.validacionPublica(token),
      );
      return response.data;
    } catch (error) {
      if (axios.isAxiosError<ApiResponse<ValidacionCertificado>>(error)
        && error.response?.status === 404
        && error.response.data.data) {
        return error.response.data;
      }
      throw error;
    }
  },
};
