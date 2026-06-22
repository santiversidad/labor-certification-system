import type { SolicitudCertificacion } from '../types/solicitud.types';

export function canApproveSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['rechazada', 'certificado_generado', 'cerrada'].includes(solicitud.estado);
}

export function canRejectSolicitud(solicitud: SolicitudCertificacion): boolean {
  return !['rechazada', 'certificado_generado', 'cerrada'].includes(solicitud.estado);
}

export function canGenerateCertificate(solicitud: SolicitudCertificacion): boolean {
  if (solicitud.estado === 'rechazada') {
    return false;
  }

  const pagoAprobado = !solicitud.incluyeSalario || solicitud.pagoEstado === 'aprobado' || solicitud.pagoEstado === 'no_requerido';
  return ['aprobada', 'certificado_generado'].includes(solicitud.estado) && pagoAprobado && solicitud.estado !== 'certificado_generado';
}
