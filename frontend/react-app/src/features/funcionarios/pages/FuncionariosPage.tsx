import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { PageHeader } from '../../../components/ui/PageHeader';
import { cargosService } from '../../cargos/services/cargos.service';
import { FuncionariosTable } from '../components/FuncionariosTable';
import { funcionariosService } from '../services/funcionarios.service';

export function FuncionariosPage() {
  const client = useQueryClient();
  const [page, setPage] = useState(1); const [q, setQ] = useState(''); const [cargo, setCargo] = useState(''); const [dependencia, setDependencia] = useState(''); const [estado, setEstado] = useState('');
  const filters = { page, per_page: 15, q: q || undefined, cargo_id: cargo || undefined, dependencia: dependencia || undefined, estado: estado || undefined };
  const query = useQuery({ queryKey: ['funcionarios', filters], queryFn: () => funcionariosService.list(filters) });
  const cargos = useQuery({ queryKey: ['cargos-opciones'], queryFn: cargosService.list });
  const reset = useMutation({ mutationFn: funcionariosService.resetAccess, onSuccess: () => client.invalidateQueries({ queryKey: ['funcionarios'] }) });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;

  return (
    <div className="space-y-6">
      <PageHeader eyebrow="Talento Humano" title="Funcionarios" description="Administre vinculación, cargo y acceso al autoservicio." />
      <div className="flex justify-end"><Link to="/admin/funcionarios/nuevo"><Button icon={<Plus size={17} />} type="button">Crear funcionario</Button></Link></div>
      <section className="space-y-4 rounded-xl border border-border bg-white p-5">
        <div className="grid gap-3 lg:grid-cols-[1.4fr_1fr_1fr_180px]">
          <label className="relative"><Search className="absolute left-3 top-3 text-muted" size={16} /><input aria-label="Buscar por cédula o nombre" className="w-full rounded-lg border border-border py-2.5 pl-9 pr-3 text-sm" onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Cédula o nombre" value={q} /></label>
          <select aria-label="Filtrar por cargo" className="rounded-lg border border-border px-3 text-sm" onChange={(e) => { setCargo(e.target.value); setPage(1); }} value={cargo}><option value="">Todos los cargos</option>{cargos.data?.data.map((item) => <option key={item.id} value={item.id}>{item.denominacion}</option>)}</select>
          <input aria-label="Filtrar por dependencia" className="rounded-lg border border-border px-3 text-sm" onChange={(e) => { setDependencia(e.target.value); setPage(1); }} placeholder="Dependencia" value={dependencia} />
          <select aria-label="Filtrar por estado" className="rounded-lg border border-border px-3 text-sm" onChange={(e) => { setEstado(e.target.value); setPage(1); }} value={estado}><option value="">Todos los estados</option><option value="activo">Activo</option><option value="retirado">Retirado</option><option value="suspendido">Suspendido</option></select>
        </div>
        <FuncionariosTable funcionarios={query.data.data} onReset={(id) => { if (window.confirm('¿Restablecer el acceso y revocar sesiones activas?')) reset.mutate(id); }} />
        <div className="flex items-center justify-between border-t border-border pt-4 text-sm text-muted"><span>{query.data.meta?.total ?? query.data.data.length} funcionarios</span><div className="flex gap-2"><Button disabled={page <= 1} onClick={() => setPage((value) => value - 1)} type="button" variant="secondary">Anterior</Button><span className="px-2 py-2">Página {page} de {query.data.meta?.last_page ?? 1}</span><Button disabled={page >= (query.data.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)} type="button" variant="secondary">Siguiente</Button></div></div>
      </section>
    </div>
  );
}
