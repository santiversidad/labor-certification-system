import type { Certificado } from '../../features/certificados/types/certificado.types';

export const mockCertificados: Certificado[] = [
  {
    id: 'cer-001',
    solicitudId: 'sol-002',
    solicitudRadicado: 'CLV-2026-0002',
    funcionarioNombre: 'Secretario Demo',
    codigoValidacion: 'VVC-2026-8F3K2A',
    estado: 'vigente',
    fechaGeneracion: '2026-05-13',
    fechaVencimiento: '2026-08-13',
    descargaUrl: '#',
  },
];
