import type { ApiResponse } from './api.types';
import type { PaginatedResponse } from '../../types/pagination.types';

const MOCK_LATENCY_MS = 250;

export function waitForMock<T>(payload: T): Promise<T> {
  return new Promise((resolve) => {
    window.setTimeout(() => resolve(payload), MOCK_LATENCY_MS);
  });
}

export function mockResponse<T>(data: T, message = 'Respuesta mock generada correctamente'): Promise<ApiResponse<T>> {
  return waitForMock({ success: true, data, message });
}

export function mockPaginatedResponse<T>(data: T[], message = 'Listado mock generado correctamente'): Promise<ApiResponse<PaginatedResponse<T>>> {
  return waitForMock({
    data: {
      data,
      meta: {
        current_page: 1,
        per_page: data.length || 10,
        total: data.length,
        last_page: 1,
      },
    },
    message,
    success: true,
  });
}
