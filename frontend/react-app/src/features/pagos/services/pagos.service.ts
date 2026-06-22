import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PagoSoporte } from '../types/pago.types';

export const pagosService = {
  async list(): Promise<ApiResponse<PagoSoporte[]>> {
    const response = await apiClient.get<ApiResponse<PagoSoporte[]>>(endpoints.pagos);
    return response.data;
  },

  async cargarSoporte(
    solicitudId: string | number,
    archivo: File,
    observaciones?: string,
  ): Promise<ApiResponse<PagoSoporte>> {
    const formData = new FormData();
    formData.append('archivo', archivo);
    if (observaciones) {
      formData.append('observaciones', observaciones);
    }

    const response = await apiClient.post<ApiResponse<PagoSoporte>>(
      `${endpoints.solicitudes}/${solicitudId}/soporte-pago`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
    return response.data;
  },

  async validar(id: string | number, observaciones?: string): Promise<ApiResponse<PagoSoporte>> {
    const response = await apiClient.patch<ApiResponse<PagoSoporte>>(
      `${endpoints.pagos}/${id}/validar`,
      { observaciones },
    );
    return response.data;
  },

  async rechazar(id: string | number, observaciones: string): Promise<ApiResponse<PagoSoporte>> {
    const response = await apiClient.patch<ApiResponse<PagoSoporte>>(
      `${endpoints.pagos}/${id}/rechazar`,
      { observaciones },
    );
    return response.data;
  },
};
