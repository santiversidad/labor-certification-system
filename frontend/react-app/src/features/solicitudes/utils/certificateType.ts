import type { TipoCertificado } from '../types/solicitud.types';

export const CERTIFICATE_TYPE_LABELS: Record<TipoCertificado, string> = {
  sencillo: 'Certificado laboral sencillo',
  funciones: 'Certificado laboral con funciones',
};

export const CERTIFICATE_TYPE_SHORT_LABELS: Record<TipoCertificado, string> = {
  sencillo: 'Sencillo',
  funciones: 'Con funciones',
};

export const CERTIFICATE_TYPE_DESCRIPTIONS: Record<TipoCertificado, string> = {
  sencillo: 'Constancia de vinculación laboral y datos del empleo.',
  funciones: 'Incluye la información laboral y las funciones correspondientes al empleo según el Manual aplicable.',
};

export function certificateTypeLabel(tipo?: string | null): string {
  if (tipo === 'sencillo' || tipo === 'funciones') return CERTIFICATE_TYPE_LABELS[tipo];
  return 'Registro histórico de modelo anterior';
}
