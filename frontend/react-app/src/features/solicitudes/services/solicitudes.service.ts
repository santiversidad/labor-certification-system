import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockPaginatedResponse, mockResponse } from '../../../lib/api/mockAdapter';
import { mockSolicitudes } from '../../../lib/mocks/mockSolicitudes';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { PaginatedResponse } from '../../../types/pagination.types';
import type { SolicitudFormValues } from '../schemas/solicitud.schema';
import type { SolicitudCertificacion } from '../types/solicitud.types';

export const solicitudesService = {
  async list(): Promise<ApiResponse<PaginatedResponse<SolicitudCertificacion>>> {
    if (env.useMocks) {
      return mockPaginatedResponse(mockSolicitudes);
    }

    const response = await apiClient.get<ApiResponse<PaginatedResponse<SolicitudCertificacion>>>(endpoints.solicitudes);
    return response.data;
  },

  async getById(id: string): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      const solicitud = mockSolicitudes.find((item) => item.id === id) ?? mockSolicitudes[0];
      return mockResponse(solicitud);
    }

    const response = await apiClient.get<ApiResponse<SolicitudCertificacion>>(`${endpoints.solicitudes}/${id}`);
    return response.data;
  },

  async create(payload: SolicitudFormValues): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      return mockResponse({
        id: `sol-${Date.now()}`,
        funcionarioId: 'fun-001',
        radicado: `CLV-2026-${String(mockSolicitudes.length + 1).padStart(4, '0')}`,
        tipo: payload.tipo,
        incluyeSalario: payload.incluyeSalario,
        estado: 'pendiente',
        fechaSolicitud: new Date().toISOString().slice(0, 10),
        observaciones: payload.observaciones,
      }, 'Solicitud mock creada correctamente.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudes, payload);
    return response.data;
  },
};
