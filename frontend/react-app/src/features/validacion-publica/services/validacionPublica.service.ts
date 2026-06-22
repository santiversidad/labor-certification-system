import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockResponse } from '../../../lib/api/mockAdapter';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export const validacionPublicaService = {
  async validate(token: string): Promise<ApiResponse<ValidacionCertificado>> {
    if (env.useMocks) {
      if (!token || token.toLowerCase() === 'invalido') {
        return mockResponse({
          valido: false,
          estado: 'anulado',
          mensaje: 'El token no corresponde a un certificado vigente o no fue encontrado.',
        });
      }

      return mockResponse({
        valido: true,
        codigoValidacion: token,
        funcionario: 'Carlos Andres Martinez Silva',
        cargo: 'Tecnico Administrativo',
        estado: 'vigente',
        fechaGeneracion: '2026-05-13',
      });
    }

    const response = await apiClient.get<ApiResponse<ValidacionCertificado>>(endpoints.validacionPublica(token));
    return response.data;
  },
};
