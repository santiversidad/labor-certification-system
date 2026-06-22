import type { PaginationMeta } from '../../types/pagination.types';

/**
 * Respuesta estándar del backend Laravel.
 *
 * Para endpoints paginados el backend envía meta al nivel raíz,
 * NO anidado dentro de data:
 *   { success, message, data: [...], meta: {...} }
 */
export type ApiResponse<T> = {
  success: boolean;
  data: T;
  message?: string;
  meta?: PaginationMeta;
};

export type ApiError = {
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
};
