import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { SolicitudFormValues } from '../schemas/solicitud.schema';
import type { DisponibilidadCertificacion, ExpedicionCertificacion, SolicitudCertificacion } from '../types/solicitud.types';

export const solicitudesService = {
  async disponibilidad(): Promise<ApiResponse<DisponibilidadCertificacion>> {
    const response = await apiClient.get<ApiResponse<DisponibilidadCertificacion>>(endpoints.disponibilidadCertificacion);
    return response.data;
  },
  async list(): Promise<ApiResponse<SolicitudCertificacion[]>> {
    const response = await apiClient.get<ApiResponse<SolicitudCertificacion[]>>(endpoints.solicitudes);
    return response.data;
  },
  async getById(id: string | number): Promise<ApiResponse<SolicitudCertificacion>> {
    const response = await apiClient.get<ApiResponse<SolicitudCertificacion>>(`${endpoints.solicitudes}/${id}`);
    return response.data;
  },
  async create(payload: SolicitudFormValues): Promise<ApiResponse<ExpedicionCertificacion>> {
    const response = await apiClient.post<ApiResponse<ExpedicionCertificacion>>(endpoints.solicitudes, payload);
    return response.data;
  },
  async downloadImmediate(url: string): Promise<{ blob: Blob; filename: string }> {
    // The API returns a root-relative path; Axios already has /api/v1 in baseURL.
    // Reject other origins/routes so the bearer token never follows an arbitrary URL.
    if (!/^\/api\/v1\/mi-certificacion\/descargar\/[a-f0-9]{64}$/.test(url)) {
      throw new Error('El enlace de descarga no es válido.');
    }
    const response = await apiClient.get<Blob>(url.slice('/api/v1'.length), { responseType: 'blob' });
    const disposition = response.headers['content-disposition'] as string | undefined;
    const match = disposition?.match(/filename="?([^";]+)"?/i);
    return { blob: response.data, filename: match?.[1] ?? 'certificacion-laboral.pdf' };
  },
};
