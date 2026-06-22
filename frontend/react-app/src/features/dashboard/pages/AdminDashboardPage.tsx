import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { useAuth } from '../../auth/hooks/useAuth';
import { isAdmin } from '../../../lib/auth/permissions';
import { auditoriaService } from '../../auditoria/services/auditoria.service';
import { certificadosService } from '../../certificados/services/certificados.service';
import { funcionariosService } from '../../funcionarios/services/funcionarios.service';
import { pagosService } from '../../pagos/services/pagos.service';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import { AdminAuditSummary } from '../components/AdminAuditSummary';
import { AdminPendingRequestsTable } from '../components/AdminPendingRequestsTable';
import { AdminQuickActions } from '../components/AdminQuickActions';
import { AdminStatsGrid } from '../components/AdminStatsGrid';

export function AdminDashboardPage() {
  const { user } = useAuth();
  const esAdmin = isAdmin(user);

  const solicitudesQuery = useQuery({ queryKey: ['admin-solicitudes'], queryFn: solicitudesService.list });
  const pagosQuery       = useQuery({ queryKey: ['admin-pagos'],       queryFn: pagosService.list });
  const certificadosQuery = useQuery({ queryKey: ['admin-certificados'], queryFn: certificadosService.list });

  // Solo admin consulta funcionarios y auditoría
  const funcionariosQuery = useQuery({
    queryKey: ['admin-funcionarios'],
    queryFn: funcionariosService.list,
    enabled: esAdmin,
  });
  const auditoriaQuery = useQuery({
    queryKey: ['admin-auditoria'],
    queryFn: auditoriaService.list,
    enabled: esAdmin,
  });

  const cargandoBase =
    solicitudesQuery.isLoading || pagosQuery.isLoading || certificadosQuery.isLoading;

  if (cargandoBase) {
    return <LoadingState />;
  }

  const solicitudes       = solicitudesQuery.data?.data ?? [];
  const pagosPorValidar   = (pagosQuery.data?.data ?? []).filter((p) => p.estado === 'pendiente').length;
  const certificadosTotal = certificadosQuery.data?.meta?.total ?? certificadosQuery.data?.data?.length ?? 0;
  const funcionariosTotal = funcionariosQuery.data?.meta?.total ?? funcionariosQuery.data?.data?.length ?? 0;
  const auditoriaLogs     = auditoriaQuery.data?.data ?? [];

  const solicitudesPendientes = solicitudes.filter((s) =>
    ['pendiente', 'en_revision', 'requiere_pago', 'pago_pendiente'].includes(s.estado),
  ).length;

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-govBlue/15 bg-surface p-6">
        <p className="text-sm font-semibold text-govBlue">Panel Administrativo</p>
        <h1 className="mt-2 text-2xl font-semibold text-text">Control institucional de certificaciones</h1>
        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted">
          Supervise solicitudes, pagos y certificados desde una vista consolidada.
        </p>
      </div>

      <AdminStatsGrid
        certificadosGenerados={certificadosTotal}
        funcionariosRegistrados={esAdmin ? funcionariosTotal : undefined}
        pagosPorValidar={pagosPorValidar}
        solicitudesPendientes={solicitudesPendientes}
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <AdminQuickActions />
          <Card title="Solicitudes recientes" description="Listado operativo para priorizar revisión administrativa.">
            <AdminPendingRequestsTable solicitudes={solicitudes.slice(0, 10)} />
          </Card>
        </div>
        {esAdmin && <AdminAuditSummary logs={auditoriaLogs} />}
      </div>
    </div>
  );
}
