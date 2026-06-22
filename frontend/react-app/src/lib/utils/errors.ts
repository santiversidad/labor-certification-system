import type { ApiError } from '../api/api.types';

export function getErrorMessage(error: unknown): string {
  if (typeof error !== 'object' || error === null) {
    return 'Ocurrió un error inesperado.';
  }

  // Axios error: el mensaje real viene en error.response.data.message
  if ('response' in error) {
    const axiosError = error as { response?: { data?: { message?: string } } };
    const backendMessage = axiosError.response?.data?.message;
    if (backendMessage) return backendMessage;
  }

  if ('message' in error) {
    return String((error as ApiError).message);
  }

  return 'Ocurrió un error inesperado.';
}
