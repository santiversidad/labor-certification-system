import type { ApiError } from '../api/api.types';
import axios from 'axios';

export const domainErrorMessages: Record<string, string> = {
  MONTHLY_CERTIFICATE_LIMIT: 'Ya solicitó esta modalidad durante el mes actual. Podrá solicitarla nuevamente el próximo mes.',
  MANUAL_FICHA_INCOMPLETA: 'La ficha del Manual de Funciones está incompleta. Talento Humano debe revisarla antes de expedir este certificado.',
  MANUAL_FICHA_NO_ASIGNADA: 'No tiene una ficha del Manual de Funciones asignada. Comuníquese con Talento Humano para actualizar su información.',
  PAGO_NO_CONFIRMADO: 'El pago requerido aún no está confirmado. Complete el pago o espere su confirmación antes de continuar.',
  PASSWORD_CHANGE_REQUIRED: 'Por seguridad, debe crear una nueva contraseña antes de continuar.',
};

function safeMessage(message: string): string {
  if (domainErrorMessages[message]) return domainErrorMessages[message];
  if (/SQLSTATE|stack trace|exception|laravel|\/var\/www/i.test(message)) {
    return 'No fue posible completar la operación. Intente nuevamente o contacte a soporte.';
  }
  return message;
}

export function getErrorMessage(error: unknown): string {
  if (typeof error !== 'object' || error === null) {
    return 'Ocurrió un error inesperado.';
  }

  // Axios error: el mensaje real viene en error.response.data.message
  if ('response' in error) {
    const axiosError = error as { response?: { data?: { message?: string; code?: string; error_code?: string; error?: { code?: string } } } };
    const code = axiosError.response?.data?.code ?? axiosError.response?.data?.error_code ?? axiosError.response?.data?.error?.code;
    if (code && domainErrorMessages[code]) return domainErrorMessages[code];
    const backendMessage = axiosError.response?.data?.message;
    if (backendMessage) return safeMessage(backendMessage);
  }

  if ('message' in error) {
    return safeMessage(String((error as ApiError).message));
  }

  return 'Ocurrió un error inesperado.';
}

export function getValidationErrors(error: unknown): Record<string, string[]> {
  if (!axios.isAxiosError<{ errors?: Record<string, string[]> }>(error)) return {};
  return error.response?.data.errors ?? {};
}
