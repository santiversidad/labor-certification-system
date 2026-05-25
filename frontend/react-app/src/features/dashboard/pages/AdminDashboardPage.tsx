import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { auditoriaService } from '../../auditoria/services/auditoria.service';
import { certificadosService } from '../../certificados/services/certificados.service';
import { funcionariosService } from '../../funcionarios/services/funcionarios.service';
import { pagosService } from '../../pagos/services/pagos.service';
import { AdminAuditSummary } from '../components/AdminAuditSummary';
import { AdminPendingRequestsTable } from '../components/AdminPendingRequestsTable';
import { AdminQuickActions } from '../components/AdminQuickActions';
import { AdminStatsGrid } from '../components/AdminStatsGrid';
import { dashboardService } from '../services/dashboard.service';

export function AdminDashboardPage() {
  const dashboardQuery = useQuery({
    queryKey: ['admin-dashboard-summary'],
    queryFn: dashboardService.getSummary,
  });
  const funcionariosQuery = useQuery({ queryKey: ['admin-funcionarios-summary'], queryFn: funcionariosService.list });
  const pagosQuery = useQuery({ queryKey: ['admin-pagos-summary'], queryFn: pagosService.list });
  const certificadosQuery = useQuery({ queryKey: ['admin-certificados-summary'], queryFn: certificadosService.list });
  const auditoriaQuery = useQuery({ queryKey: ['admin-auditoria-summary'], queryFn: auditoriaService.list });

  if (dashboardQuery.isLoading || funcionariosQuery.isLoading || pagosQuery.isLoading || certificadosQuery.isLoading || auditoriaQuery.isLoading) {
    return <LoadingState />;
  }

  if (
    dashboardQuery.isError ||
    funcionariosQuery.isError ||
    pagosQuery.isError ||
    certificadosQuery.isError ||
    auditoriaQuery.isError ||
    !dashboardQuery.data ||
    !funcionariosQuery.data ||
    !pagosQuery.data ||
    !certificadosQuery.data ||
    !auditoriaQuery.data
  ) {
    return <ErrorState />;
  }

  const solicitudes = dashboardQuery.data.data.recentRequests;
  const solicitudesPendientes = solicitudes.filter((solicitud) => ['pendiente', 'en_revision', 'pendiente_pago', 'pago_en_revision'].includes(solicitud.estado)).length;
  const pagosPorValidar = pagosQuery.data.data.data.filter((pago) => pago.estado === 'cargado' || pago.estado === 'pendiente').length;

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-govBlue/15 bg-surface p-6">
        <p className="text-sm font-semibold text-govBlue">Panel Administrativo V2</p>
        <h1 className="mt-2 text-2xl font-semibold text-text">Control institucional de certificaciones</h1>
        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted">
          Supervise solicitudes, pagos, certificados y catalogos maestros desde una vista sobria preparada para la API Laravel.
        </p>
      </div>

      <AdminStatsGrid
        certificadosGenerados={certificadosQuery.data.data.data.length}
        funcionariosRegistrados={funcionariosQuery.data.data.meta.total}
        pagosPorValidar={pagosPorValidar}
        solicitudesPendientes={solicitudesPendientes}
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <AdminQuickActions />
          <Card title="Solicitudes recientes" description="Listado operativo para priorizar revision administrativa.">
            <AdminPendingRequestsTable solicitudes={solicitudes} />
          </Card>
        </div>
        <AdminAuditSummary logs={auditoriaQuery.data.data.data} />
      </div>
    </div>
  );
}
