import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { Certificado } from '../types/certificado.types';
import axios from 'axios';

export const certificadosService = {
  async list(): Promise<ApiResponse<Certificado[]>> {
    const response = await apiClient.get<ApiResponse<Certificado[]>>(endpoints.certificados);
    return response.data;
  },

  async getById(id: string | number): Promise<ApiResponse<Certificado>> {
    const response = await apiClient.get<ApiResponse<Certificado>>(
      `${endpoints.certificados}/${id}`,
    );
    return response.data;
  },

  async annul(id: string | number, motivo: string): Promise<ApiResponse<Certificado>> {
    const response = await apiClient.patch<ApiResponse<Certificado>>(
      `${endpoints.certificados}/${id}/anular`,
      { motivo },
    );
    return response.data;
  },

  async download(id: string | number): Promise<string> {
    let response;
    try {
      response = await apiClient.get<Blob>(`${endpoints.certificados}/${id}/descargar`, {
        responseType: 'blob',
      });
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
        const payload = JSON.parse(await error.response.data.text()) as { message?: string };
        throw new Error(
          payload.message ?? `No fue posible descargar el certificado (${error.response.status}).`,
          { cause: error },
        );
      }
      throw error;
    }
    const disposition = response.headers['content-disposition'] as string | undefined;
    const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const quoted = disposition?.match(/filename="([^"]+)"/i)?.[1];
    const filename = encoded ? decodeURIComponent(encoded) : quoted ?? `certificado-${id}.pdf`;
    const objectUrl = URL.createObjectURL(response.data);

    try {
      const anchor = document.createElement('a');
      anchor.href = objectUrl;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
    } finally {
      URL.revokeObjectURL(objectUrl);
    }

    return filename;
  },
};
