/**
 * El endpoint público de validación aún no está implementado en el backend.
 * Estructura propuesta basada en el plan del Sprint 2 módulo 4.
 */
export type ValidacionCertificado = {
  valido: boolean;
  estado: 'vigente' | 'anulado';
  codigo_unico: string;
  fecha_expedicion?: string;
  expedido_por?: string;
  tipo_certificado?: string;
  funcionario_titular?: {
    nombre_completo: string;
    documento: string;
  };
};
