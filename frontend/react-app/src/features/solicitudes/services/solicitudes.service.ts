import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { SolicitudFormValues } from '../schemas/solicitud.schema';
import type { SolicitudCertificacion } from '../types/solicitud.types';

export const solicitudesService = {
  async list(): Promise<ApiResponse<SolicitudCertificacion[]>> {
    const response = await apiClient.get<ApiResponse<SolicitudCertificacion[]>>(endpoints.solicitudes);
    return response.data;
  },

  async getById(id: string | number): Promise<ApiResponse<SolicitudCertificacion>> {
    const response = await apiClient.get<ApiResponse<SolicitudCertificacion>>(
      `${endpoints.solicitudes}/${id}`,
    );
    return response.data;
  },

  async create(payload: SolicitudFormValues): Promise<ApiResponse<SolicitudCertificacion>> {
    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(
      endpoints.solicitudes,
      payload,
    );
    return response.data;
  },

  async cambiarEstado(
    id: string | number,
    payload: { estado: string; motivo_rechazo?: string; observaciones?: string; requiere_pago?: boolean },
  ): Promise<ApiResponse<SolicitudCertificacion>> {
    const response = await apiClient.patch<ApiResponse<SolicitudCertificacion>>(
      `${endpoints.solicitudes}/${id}/estado`,
      payload,
    );
    return response.data;
  },
};
