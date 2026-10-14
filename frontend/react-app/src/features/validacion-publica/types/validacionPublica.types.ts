import type { TipoCertificado } from '../../solicitudes/types/solicitud.types';

export type ValidacionCertificado = {
  valido: boolean;
  resultado: 'valido' | 'anulado' | 'integridad_comprometida' | 'no_vigente' | 'no_encontrado';
  estado?: 'vigente' | 'descargado' | 'anulado' | 'vencido' | null;
  codigo_unico?: string;
  fecha_generacion?: string;
  tipo_certificado?: TipoCertificado | null;
  funcionario?: { nombre: string } | null;
  cargo?: string | null;
  mensaje?: string;
};
