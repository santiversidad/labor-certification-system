export type ValidacionCertificado = {
  valido: boolean;
  resultado: 'valido' | 'anulado' | 'integridad_comprometida' | 'no_vigente' | 'no_encontrado';
  estado?: 'vigente' | 'descargado' | 'anulado' | 'vencido' | null;
  codigo_unico?: string;
  fecha_generacion?: string;
  funcionario?: { nombre: string } | null;
  cargo?: string | null;
  tipo_certificado?: 'sencillo' | 'funciones' | null;
  mensaje?: string;
};
