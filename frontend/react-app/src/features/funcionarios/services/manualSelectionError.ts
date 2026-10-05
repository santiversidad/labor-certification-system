import axios from 'axios';

const messages: Record<string, { title: string; description: string }> = {
  MANUAL_FICHA_AMBIGUA: {
    title: 'Este cargo requiere revisión del Manual',
    description: 'El Manual de Funciones contiene más de un perfil aplicable para este cargo, dependencia y fecha. El sistema no puede seleccionar uno automáticamente porque podría asignar funciones incorrectas.',
  },
  MANUAL_FICHA_NO_ENCONTRADA: {
    title: 'No se encontró un perfil compatible',
    description: 'No se encontró un perfil del Manual compatible con este cargo, dependencia y fecha.',
  },
  MANUAL_FICHA_INCOMPLETA: {
    title: 'Este cargo requiere revisión del Manual',
    description: 'La ficha del Manual de Funciones está incompleta y requiere revisión de la Dirección de Personal.',
  },
  MANUAL_FICHA_INCOMPATIBLE: {
    title: 'La selección requiere revisión',
    description: 'La combinación de cargo y dependencia no es compatible con el Manual de Funciones.',
  },
  MANUAL_VERSION_NO_VIGENTE: {
    title: 'El Manual no está vigente para esta fecha',
    description: 'Seleccione un cargo, dependencia y fecha con un Manual publicado y aplicable.',
  },
};

export function manualSelectionError(error: unknown) {
  const response = axios.isAxiosError<{ code?: string }>(error) ? error.response : undefined;
  const code = response?.data?.code;
  if (code && messages[code]) return { ...messages[code], retryable: false, domain: true };
  const retryable = !response || response.status >= 500 || response.status === 408 || response.status === 429;
  return retryable ? {
    title: 'No pudimos consultar el Manual',
    description: 'Revise la conexión o inténtelo nuevamente.',
    retryable: true,
    domain: false,
  } : {
    title: 'No pudimos validar la selección',
    description: 'Revise la dependencia, el cargo y la fecha. Si la situación continúa, consulte con la Dirección de Personal.',
    retryable: false,
    domain: false,
  };
}
