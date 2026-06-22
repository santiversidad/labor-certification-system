import type { SolicitudCertificacion } from '../types/solicitud.types';

export function canApproveSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['aprobado', 'rechazado', 'generado', 'cancelado'].includes(solicitud.estado);
}

export function canRejectSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['rechazado', 'generado', 'cancelado'].includes(solicitud.estado);
}

export function canGenerateCertificate(solicitud: SolicitudCertificacion): boolean {
  if (['rechazado', 'cancelado', 'generado'].includes(solicitud.estado)) {
    return false;
  }

  if (solicitud.requiere_pago) {
    return solicitud.estado === 'pago_validado';
  }

  return solicitud.estado === 'aprobado';
}
