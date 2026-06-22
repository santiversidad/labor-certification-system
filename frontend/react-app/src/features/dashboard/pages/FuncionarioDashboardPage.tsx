import { useQuery } from '@tanstack/react-query';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { useAuth } from '../../auth/hooks/useAuth';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import { FuncionarioCertificateActions } from '../components/FuncionarioCertificateActions';
import { FuncionarioRecentRequests } from '../components/FuncionarioRecentRequests';
import { FuncionarioStatsGrid } from '../components/FuncionarioStatsGrid';
import { FuncionarioWelcomeCard } from '../components/FuncionarioWelcomeCard';
import type { DashboardMetric } from '../types/dashboard.types';

export function FuncionarioDashboardPage() {
  const { user } = useAuth();
  const solicitudesQuery = useQuery({
    queryKey: ['mis-solicitudes'],
    queryFn: solicitudesService.list,
  });

  if (solicitudesQuery.isLoading) {
    return <LoadingState />;
  }

  if (solicitudesQuery.isError || !solicitudesQuery.data) {
    return <ErrorState />;
  }

  const solicitudes = solicitudesQuery.data.data;

  const metrics: DashboardMetric[] = [
    { label: 'Pendientes', value: solicitudes.filter((s) => s.estado === 'pendiente').length, tone: 'gold' },
    { label: 'En revisión', value: solicitudes.filter((s) => s.estado === 'en_revision').length, tone: 'blue' },
    { label: 'Generadas', value: solicitudes.filter((s) => s.estado === 'generado').length, tone: 'green' },
    { label: 'Rechazadas', value: solicitudes.filter((s) => s.estado === 'rechazado').length, tone: 'red' },
  ];

  return (
    <div className="space-y-6">
      <FuncionarioWelcomeCard user={user} />
      <FuncionarioStatsGrid metrics={metrics} />
      <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
        <FuncionarioRecentRequests solicitudes={solicitudes.slice(0, 5)} />
        <FuncionarioCertificateActions />
      </div>
    </div>
  );
}
