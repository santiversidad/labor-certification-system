import type { SolicitudCertificacion } from '../types/solicitud.types';

export function canApproveSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['aprobada', 'rechazada', 'certificado_generado', 'generada', 'cerrada'].includes(solicitud.estado);
}

export function canRejectSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['rechazada', 'certificado_generado', 'generada', 'cerrada'].includes(solicitud.estado);
}

export function canGenerateCertificate(solicitud: SolicitudCertificacion): boolean {
  if (['rechazada', 'cerrada', 'certificado_generado', 'generada'].includes(solicitud.estado)) {
    return false;
  }

  if (solicitud.requiere_pago) {
    return false;
  }

  return solicitud.estado === 'aprobada';
}
