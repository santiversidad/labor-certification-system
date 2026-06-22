export type CertificadoEstado = 'vigente' | 'anulado';

export type Certificado = {
  id: number;
  codigo_unico: string;
  estado: CertificadoEstado;
  fecha_generacion?: string | null;
  created_at?: string;

  generado_por?: { id: number; name: string };
};
