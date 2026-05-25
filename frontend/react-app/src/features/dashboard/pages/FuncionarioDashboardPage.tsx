import { useQuery } from '@tanstack/react-query';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { useAuth } from '../../auth/hooks/useAuth';
import { FuncionarioCertificateActions } from '../components/FuncionarioCertificateActions';
import { FuncionarioRecentRequests } from '../components/FuncionarioRecentRequests';
import { FuncionarioStatsGrid } from '../components/FuncionarioStatsGrid';
import { FuncionarioWelcomeCard } from '../components/FuncionarioWelcomeCard';
import { dashboardService } from '../services/dashboard.service';

export function FuncionarioDashboardPage() {
  const { user } = useAuth();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['dashboard-summary', user?.roles.join(',')],
    queryFn: dashboardService.getSummary,
  });

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  return (
    <div className="space-y-6">
      <FuncionarioWelcomeCard user={user} />
      <FuncionarioStatsGrid metrics={data.data.metrics} />
      <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
        <FuncionarioRecentRequests solicitudes={data.data.recentRequests} />
        <FuncionarioCertificateActions />
      </div>
    </div>
  );
}
