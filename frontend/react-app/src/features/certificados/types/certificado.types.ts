export type CertificadoEstado = 'vigente' | 'descargado' | 'anulado' | 'vencido';

export type Certificado = {
  id: number;
  codigo_unico: string;
  estado: CertificadoEstado;
  fecha_generacion?: string | null;
  created_at?: string;
  generado_por?: { id: number; name: string };
  motivo_anulacion?: string | null;
  snapshot_schema_version?: number | null;
  snapshot_datos?: Record<string, unknown>;
  solicitud?: { id: number; radicado: string; estado: string } | null;
  funcionario?: { id: number; nombres: string; apellidos: string; numero_documento: string } | null;
};

export type GenerarCertificadoData = {
  certificado: Certificado;
  token: string;
  url_validacion: string;
};
