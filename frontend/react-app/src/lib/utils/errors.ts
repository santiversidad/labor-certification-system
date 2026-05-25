import type { ApiError } from '../api/api.types';

export function getErrorMessage(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    return String((error as ApiError).message);
  }

  return 'Ocurrio un error inesperado.';
}
