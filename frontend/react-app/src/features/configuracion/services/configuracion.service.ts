import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';

export type ConfiguracionCertificaciones = { requiere_pago_certificado: boolean; descripcion: string };

export const configuracionService = {
  async get(): Promise<ApiResponse<ConfiguracionCertificaciones>> {
    return (await apiClient.get<ApiResponse<ConfiguracionCertificaciones>>(endpoints.configuracionCertificaciones)).data;
  },
  async update(requierePago: boolean): Promise<ApiResponse<ConfiguracionCertificaciones>> {
    return (await apiClient.patch<ApiResponse<ConfiguracionCertificaciones>>(endpoints.configuracionCertificaciones, { requiere_pago_certificado: requierePago })).data;
  },
};
