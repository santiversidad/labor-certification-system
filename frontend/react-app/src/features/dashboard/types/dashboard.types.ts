import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

export type DashboardMetric = {
  label: string;
  value: number;
  tone: 'blue' | 'green' | 'red' | 'gold';
};

export type DashboardSummary = {
  metrics: DashboardMetric[];
  recentRequests: SolicitudCertificacion[];
};
