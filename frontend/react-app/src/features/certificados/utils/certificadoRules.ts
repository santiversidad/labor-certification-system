import type { Certificado } from '../types/certificado.types';

export function canDownloadCertificado(certificado: Certificado): boolean {
  return certificado.estado === 'vigente' && Boolean(certificado.descargaUrl);
}
