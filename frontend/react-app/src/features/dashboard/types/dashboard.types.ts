import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

export type DashboardMetric = {
  label: string;
  value: number;
  tone: 'blue' | 'green' | 'red' | 'gold';
};

/**
 * El endpoint /dashboard aún no existe en el backend (pendiente Sprint 2).
 * Mantenemos el tipo para que el frontend funcione cuando se implemente.
 */
export type DashboardSummary = {
  metrics: DashboardMetric[];
  recentRequests: SolicitudCertificacion[];
};
