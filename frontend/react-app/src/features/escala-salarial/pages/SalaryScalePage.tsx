import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { useAuth } from '../../auth/hooks/useAuth';
import { hasAnyRole } from '../../../lib/auth/permissions';
import { YearDialog } from '../components/YearDialog';
import { ItemDialog } from '../components/ItemDialog';
import { YearDetailsDialog } from '../components/YearDetailsDialog';
import { SalaryTable } from '../components/SalaryTable';
import { salaryScaleService } from '../services/salary-scale.service';
import { salaryError } from '../schemas/salary-scale.schema';
import type { SalaryItem, SalaryYear } from '../types/salary-scale.types';

type Dialog = { kind: 'create' } | { kind: 'increase' | 'publish' | 'details'; year: SalaryYear } | { kind: 'item'; item: SalaryItem; year: SalaryYear };
export function SalaryScalePage() {
  const { user } = useAuth();
  const admin = hasAnyRole(user, ['admin']);
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState('');
  const client = useQueryClient();
  const query = useQuery({ queryKey: ['escala-salarial', q, page], queryFn: () => salaryScaleService.list(q, page), enabled: admin, retry: false, refetchOnWindowFocus: false });
  async function saved() { await client.invalidateQueries({ queryKey: ['escala-salarial'] }); setDialog(null); setNotice('Cambio guardado. La tabla muestra los valores registrados.'); }
  if (!admin) return <p role="alert">Esta sección está disponible únicamente para administradores.</p>;
  const data = query.data;
  const current = new Date().getFullYear();
  return <div className="min-w-0 space-y-5">
    <header className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-2xl font-semibold text-text">Escala salarial</h1><p className="mt-1 text-sm text-muted">Referencia administrativa de asignación básica mensual por cargo y vigencia.</p></div><div className="flex flex-wrap gap-2"><Button type="button" variant="secondary" disabled={query.isFetching} onClick={() => { setNotice(''); void query.refetch(); }}>Actualizar tabla</Button><Button type="button" disabled={!data || query.isFetching} onClick={() => { setNotice(''); setDialog({ kind: 'create' }); }}>Crear vigencia</Button></div></header>
    <p className="border-l-4 border-primary bg-surface-muted px-4 py-3 text-sm">Los borradores y los cálculos porcentuales requieren revisión. La publicación en el sistema es una referencia operativa; el acto administrativo se registra por separado.</p>
    {data && !data.vigencias.some((year) => year.anio === current) && <p className="text-sm text-amber-900" role="status">{current} · Pendiente de registrar</p>}
    <form className="flex max-w-xl items-end gap-2" onSubmit={(event) => { event.preventDefault(); setPage(1); setQ(search.trim()); }}><div className="min-w-0 flex-1"><Input label="Buscar por código, grado o denominación" maxLength={150} value={search} onChange={(event) => setSearch(event.target.value)} /></div><Button type="submit" variant="secondary">Buscar</Button></form>
    {notice && <p className="text-sm text-success" role="status">{notice}</p>}
    {query.isLoading && <p role="status">Consultando escala salarial…</p>}
    {query.isError && <div role="alert" className="space-y-2"><p>{salaryError(query.error)}</p><Button type="button" onClick={() => { void query.refetch(); }}>Reintentar</Button></div>}
    {data && <>
      {!data.filas.length ? <p className="rounded-md border border-border p-5 text-sm">{data.vigencias.length ? 'No hay filas que coincidan con la búsqueda.' : 'No hay vigencias registradas. Importe una transcripción revisada de la fuente institucional o cree un borrador para registrar sus valores.'}</p> : <SalaryTable data={data} onEdit={(item, year) => setDialog({ kind: 'item', item, year })} onIncrease={(year) => setDialog({ kind: 'increase', year })} onPublish={(year) => setDialog({ kind: 'publish', year })} onDetails={(year) => setDialog({ kind: 'details', year })} />}
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm"><p>{data.meta.total} filas · Página {data.meta.current_page} de {data.meta.last_page}</p><div className="flex gap-2"><Button type="button" variant="secondary" disabled={page <= 1 || query.isFetching} onClick={() => setPage(page - 1)}>Anterior</Button><Button type="button" variant="secondary" disabled={page >= data.meta.last_page || query.isFetching} onClick={() => setPage(page + 1)}>Siguiente</Button></div></div>
      <p className="text-xs text-muted">El número de cargos pertenece a cada vigencia. En pantallas pequeñas, desplace la tabla horizontalmente para comparar los años.</p>
      {dialog?.kind === 'create' && <YearDialog years={data.vigencias} onClose={() => setDialog(null)} onSaved={saved} />}
      {dialog?.kind === 'increase' && <YearDialog years={data.vigencias} target={dialog.year} onClose={() => setDialog(null)} onSaved={saved} />}
      {dialog?.kind === 'item' && <ItemDialog item={dialog.item} year={dialog.year.anio} onClose={() => setDialog(null)} onSaved={saved} />}
      {(dialog?.kind === 'publish' || dialog?.kind === 'details') && <YearDetailsDialog year={dialog.year} publish={dialog.kind === 'publish'} onClose={() => setDialog(null)} onSaved={saved} />}
    </>}
  </div>;
}
