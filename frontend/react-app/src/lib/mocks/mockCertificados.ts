import type { Certificado } from '../../features/certificados/types/certificado.types';

export const mockCertificados: Certificado[] = [
  {
    id: 'cer-001',
    solicitudId: 'sol-002',
    codigoValidacion: 'VVC-2026-8F3K2A',
    estado: 'generado',
    fechaGeneracion: '2026-05-13',
    fechaVencimiento: '2026-08-13',
    descargaUrl: '#',
  },
];
