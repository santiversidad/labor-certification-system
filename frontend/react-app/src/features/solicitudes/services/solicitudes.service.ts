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
        pagoEstado: payload.incluyeSalario ? 'pendiente' : 'no_requerido',
        eventos: [
          {
            id: `evt-${Date.now()}`,
            titulo: 'Solicitud registrada',
            fecha: new Date().toISOString().slice(0, 10),
            descripcion: 'La solicitud fue enviada por el funcionario.',
          },
        ],
      }, 'Solicitud creada correctamente.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudes, payload);
    return response.data;
  },

  async approve(id: string): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      const solicitud = mockSolicitudes.find((item) => item.id === id) ?? mockSolicitudes[0];
      return mockResponse({ ...solicitud, estado: 'aprobada' }, 'Solicitud aprobada.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudesActions.aprobar(id));
    return response.data;
  },

  async reject(id: string, observacion: string): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      const solicitud = mockSolicitudes.find((item) => item.id === id) ?? mockSolicitudes[0];
      return mockResponse({ ...solicitud, estado: 'rechazada', observaciones: observacion }, 'Solicitud rechazada.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudesActions.rechazar(id), { observacion });
    return response.data;
  },

  async markPaymentPending(id: string): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      const solicitud = mockSolicitudes.find((item) => item.id === id) ?? mockSolicitudes[0];
      return mockResponse({ ...solicitud, estado: 'pendiente_pago', pagoEstado: 'pendiente' }, 'Solicitud marcada como pendiente de pago.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudesActions.pendientePago(id));
    return response.data;
  },

  async generateCertificate(id: string): Promise<ApiResponse<SolicitudCertificacion>> {
    if (env.useMocks) {
      const solicitud = mockSolicitudes.find((item) => item.id === id) ?? mockSolicitudes[0];
      return mockResponse({ ...solicitud, estado: 'certificado_generado' }, 'Certificado generado.');
    }

    const response = await apiClient.post<ApiResponse<SolicitudCertificacion>>(endpoints.solicitudesActions.generarCertificado(id));
    return response.data;
  },
};
