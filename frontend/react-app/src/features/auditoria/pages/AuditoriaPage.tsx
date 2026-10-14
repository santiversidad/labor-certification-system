import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Input } from '../../../components/ui/Input';
import { PageHeader } from '../../../components/ui/PageHeader';
import { AuditoriaTable } from '../components/AuditoriaTable';
import { auditoriaService } from '../services/auditoria.service';

export function AuditoriaPage() {
  const [moduleFilter, setModuleFilter] = useState('');
  const [userFilter, setUserFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('');
  const query = useQuery({ queryKey: ['auditoria'], queryFn: auditoriaService.list });
  const filteredLogs = useMemo(() => {
    const logs = query.data?.data ?? [];
    return logs.filter((log) => {
      const matchesModule = moduleFilter ? (log.modelo ?? '').toLowerCase().includes(moduleFilter.toLowerCase()) : true;
      const matchesUser = userFilter ? (log.user?.name ?? '').toLowerCase().includes(userFilter.toLowerCase()) : true;
      const matchesDate = dateFilter ? log.created_at.startsWith(dateFilter) : true;
      return matchesModule && matchesUser && matchesDate;
    });
  }, [dateFilter, moduleFilter, query.data, userFilter]);

  return (
    <div className="app-page">
      <PageHeader eyebrow="Trazabilidad" title="Auditoría" description="Consulte actividad del sistema por módulo, usuario y fecha." />
      <section className="surface-section space-y-5 p-5 sm:p-6">
        <div className="grid gap-4 md:grid-cols-3">
          <Input label="Recurso o módulo" onChange={(event) => setModuleFilter(event.target.value)} placeholder="Ej. Funcionario" value={moduleFilter} />
          <Input label="Usuario" onChange={(event) => setUserFilter(event.target.value)} placeholder="Nombre" value={userFilter} />
          <Input label="Fecha" onChange={(event) => setDateFilter(event.target.value)} type="date" value={dateFilter} />
        </div>
        {query.isError ? <ErrorState message="No fue posible consultar los eventos de auditoría." /> : null}
        <AuditoriaTable loading={query.isLoading} logs={filteredLogs} />
      </section>
    </div>
  );
}
