import type { CertificadoEstado } from '../../certificados/types/certificado.types';

export type ValidacionCertificado = {
  valido: boolean;
  codigoValidacion: string;
  funcionario: string;
  cargo: string;
  estado: CertificadoEstado;
  fechaGeneracion: string;
};
