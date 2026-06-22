import type { SolicitudCertificacion } from '../../features/solicitudes/types/solicitud.types';

export const mockSolicitudes: SolicitudCertificacion[] = [
  {
    id: 'sol-001',
    funcionarioId: 'fun-001',
    funcionarioNombre: 'Funcionario Demo',
    funcionarioDocumento: '000000003',
    cargoActual: 'Profesional universitario grado 03',
    dependencia: 'Secretaria Administrativa',
    radicado: 'CLV-2026-0001',
    tipo: 'laboral',
    incluyeSalario: false,
    estado: 'pendiente',
    fechaSolicitud: '2026-05-18',
    observaciones: 'Certificacion para tramite bancario.',
    pagoEstado: 'no_requerido',
    eventos: [
      {
        id: 'evt-001',
        titulo: 'Solicitud registrada',
        fecha: '2026-05-18',
        descripcion: 'El funcionario envio la solicitud de certificacion laboral.',
      },
    ],
  },
  {
    id: 'sol-002',
    funcionarioId: 'fun-002',
    funcionarioNombre: 'Secretario Demo',
    funcionarioDocumento: '000000002',
    cargoActual: 'Secretario de despacho',
    dependencia: 'Secretaria General',
    radicado: 'CLV-2026-0002',
    tipo: 'salarial',
    incluyeSalario: true,
    estado: 'certificado_generado',
    fechaSolicitud: '2026-05-12',
    pagoEstado: 'aprobado',
    eventos: [
      {
        id: 'evt-002',
        titulo: 'Solicitud aprobada',
        fecha: '2026-05-12',
        descripcion: 'La solicitud fue revisada y aprobada.',
      },
      {
        id: 'evt-003',
        titulo: 'Certificado generado',
        fecha: '2026-05-13',
      },
    ],
  },
];
