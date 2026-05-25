import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockResponse } from '../../../lib/api/mockAdapter';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export const validacionPublicaService = {
  async validate(token: string): Promise<ApiResponse<ValidacionCertificado>> {
    if (env.useMocks) {
      return mockResponse({
        valido: true,
        codigoValidacion: token,
        funcionario: 'Carlos Andres Martinez Silva',
        cargo: 'Tecnico Administrativo',
        estado: 'generado',
        fechaGeneracion: '2026-05-13',
      });
    }

    const response = await apiClient.get<ApiResponse<ValidacionCertificado>>(endpoints.validacionPublica(token));
    return response.data;
  },
};
