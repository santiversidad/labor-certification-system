export type CertificadoEstado = 'generado' | 'descargado' | 'vencido' | 'anulado';

export type Certificado = {
  id: string;
  solicitudId: string;
  codigoValidacion: string;
  estado: CertificadoEstado;
  fechaGeneracion: string;
  fechaVencimiento: string;
  descargaUrl?: string;
};
