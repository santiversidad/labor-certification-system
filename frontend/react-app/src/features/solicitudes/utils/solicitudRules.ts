import type { SolicitudCertificacion } from '../types/solicitud.types';

const ESTADOS_TERMINALES = ['aprobada', 'rechazada', 'certificado_generado', 'cerrada'] as const;

export function canApproveSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !ESTADOS_TERMINALES.includes(solicitud.estado as typeof ESTADOS_TERMINALES[number]);
}

export function canRejectSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !ESTADOS_TERMINALES.includes(solicitud.estado as typeof ESTADOS_TERMINALES[number]);
}

export function canGenerateCertificate(solicitud: SolicitudCertificacion): boolean {
  return solicitud.estado === 'aprobada';
}
