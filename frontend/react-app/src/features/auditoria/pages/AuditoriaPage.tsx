import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { EmptyState } from '../../../components/ui/EmptyState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { AuditoriaTable } from '../components/AuditoriaTable';
import { auditoriaService } from '../services/auditoria.service';

export function AuditoriaPage() {
  const [moduleFilter, setModuleFilter] = useState('');
  const [userFilter, setUserFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['auditoria'],
    queryFn: auditoriaService.list,
  });
  const filteredLogs = useMemo(() => {
    const logs = data?.data.data ?? [];

    return logs.filter((log) => {
      const matchesModule = moduleFilter ? log.module.toLowerCase().includes(moduleFilter.toLowerCase()) : true;
      const matchesUser = userFilter ? log.userName.toLowerCase().includes(userFilter.toLowerCase()) : true;
      const matchesDate = dateFilter ? log.createdAt.startsWith(dateFilter) : true;
      return matchesModule && matchesUser && matchesDate;
    });
  }, [data, dateFilter, moduleFilter, userFilter]);

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Auditoria" description="Consulte actividad del sistema por modulo, usuario y fecha." />
      <Card title="Filtros">
        <div className="grid gap-3 md:grid-cols-3">
          <input className="min-h-10 rounded-md border border-border bg-surface px-3 text-sm" placeholder="Modulo" value={moduleFilter} onChange={(event) => setModuleFilter(event.target.value)} />
          <input className="min-h-10 rounded-md border border-border bg-surface px-3 text-sm" placeholder="Usuario" value={userFilter} onChange={(event) => setUserFilter(event.target.value)} />
          <input className="min-h-10 rounded-md border border-border bg-surface px-3 text-sm" type="date" value={dateFilter} onChange={(event) => setDateFilter(event.target.value)} />
        </div>
      </Card>
      <Card title="Eventos recientes" description="Estructura preparada para endpoint real de trazabilidad.">
        {filteredLogs.length ? <AuditoriaTable logs={filteredLogs} /> : <EmptyState title="Sin eventos" description="No hay registros que coincidan con los filtros." />}
      </Card>
    </div>
  );
}
