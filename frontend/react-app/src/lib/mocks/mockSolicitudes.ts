import type { SolicitudCertificacion } from '../../features/solicitudes/types/solicitud.types';

export const mockSolicitudes: SolicitudCertificacion[] = [
  {
    id: 'sol-001',
    funcionarioId: 'fun-001',
    radicado: 'CLV-2026-0001',
    tipo: 'laboral',
    estado: 'pendiente',
    fechaSolicitud: '2026-05-18',
    observaciones: 'Certificacion para tramite bancario.',
  },
  {
    id: 'sol-002',
    funcionarioId: 'fun-002',
    radicado: 'CLV-2026-0002',
    tipo: 'salarial',
    estado: 'certificado_generado',
    fechaSolicitud: '2026-05-12',
  },
];
