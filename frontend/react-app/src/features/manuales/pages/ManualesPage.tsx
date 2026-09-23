import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, CheckCircle2, FileDiff, FileUp, LockKeyhole, Plus, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { PageHeader } from '../../../components/ui/PageHeader';
import { manualesService, type ManualDiff } from '../services/manuales.service';

const pasos = ['Crear nueva versión', 'Cargar JSON o Excel', 'Ejecutar dry-run', 'Comparar versiones', 'Resolver ambigüedades', 'Mapear funcionarios', 'Publicar', 'Activar desde fecha efectiva'];

export function ManualesPage() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ['manuales-estado'], queryFn: manualesService.estado });
  const [crear, setCrear] = useState(false);
  const [diff, setDiff] = useState<ManualDiff | null>(null);
  const [archivo, setArchivo] = useState<File | null>(null);
  const [fecha, setFecha] = useState('');
  const [form, setForm] = useState({ version: '', vigencia_desde: '', vigencia_hasta: '', acto_tipo: 'Decreto', acto_numero: '', acto_fecha: '', acto_referencia: '' });
  const refresh = () => client.invalidateQueries({ queryKey: ['manuales-estado'] });
  const createMutation = useMutation({ mutationFn: () => manualesService.crearVersion(query.data!.data.versiones[0].manual_id, form), onSuccess: () => { setCrear(false); refresh(); } });
  const importMutation = useMutation({ mutationFn: ({ id, dry }: { id: number; dry: boolean }) => manualesService.importar(id, archivo!, dry), onSuccess: (_data, vars) => { if (!vars.dry) refresh(); } });
  const diffMutation = useMutation({ mutationFn: ({ from, to }: { from: number; to: number }) => manualesService.diff(from, to), onSuccess: (result) => setDiff(result.data) });
  const planMutation = useMutation({ mutationFn: ({ from, to }: { from: number; to: number }) => manualesService.planificar(from, to) });
  const publishMutation = useMutation({ mutationFn: (id: number) => manualesService.publicar(id, fecha), onSuccess: () => { setDiff(null); refresh(); } });

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const { versiones, manual_vigente } = query.data.data;
  const vigente = versiones.find((v) => v.id === manual_vigente);
  const borrador = versiones.find((v) => v.estado_dominio === 'borrador');

  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Gestión normativa" title="Manual de Funciones" description="Versiones completas, comparables e inmutables después de su publicación." actions={<Button icon={<Plus size={17} />} onClick={() => setCrear((value) => !value)}>Crear borrador</Button>} />

      {vigente ? <section className="overflow-hidden rounded-xl bg-govBlue text-white transition-all duration-300">
        <div className="grid gap-6 px-7 py-7 md:grid-cols-[1fr_auto] md:items-end">
          <div><p className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">Manual vigente</p><h2 className="mt-2 text-2xl font-semibold">{vigente.version}</h2><p className="mt-2 text-sm text-blue-100">{vigente.acto} · vigente hasta que una nueva versión lo sustituya</p></div>
          <div className="flex gap-7 border-t border-white/20 pt-5 md:border-l md:border-t-0 md:pl-7 md:pt-0"><Metric label="Fichas" value={vigente.fichas} /><Metric label="Funciones" value={vigente.funciones} /><Badge className="self-start bg-white/15 text-white">VIGENTE</Badge></div>
        </div>
      </section> : null}

      {crear ? <form className="grid gap-4 rounded-xl border border-govBlue/20 bg-white p-6 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); createMutation.mutate(); }}>
        <div className="md:col-span-2"><h2 className="font-semibold text-text">Nueva versión en borrador</h2><p className="mt-1 text-sm text-muted">La versión vigente no se duplica ni se sobrescribe.</p></div>
        <Input label="Nombre de versión" value={form.version} onChange={(e) => setForm({ ...form, version: e.target.value })} required />
        <Input label="Vigencia desde" type="date" value={form.vigencia_desde} onChange={(e) => setForm({ ...form, vigencia_desde: e.target.value })} required />
        <Input label="Tipo de acto" value={form.acto_tipo} onChange={(e) => setForm({ ...form, acto_tipo: e.target.value })} required />
        <Input label="Número del acto" value={form.acto_numero} onChange={(e) => setForm({ ...form, acto_numero: e.target.value })} required />
        <Input label="Fecha del acto" type="date" value={form.acto_fecha} onChange={(e) => setForm({ ...form, acto_fecha: e.target.value })} required />
        <Input label="Referencia" value={form.acto_referencia} onChange={(e) => setForm({ ...form, acto_referencia: e.target.value })} />
        <div className="flex gap-3 md:col-span-2"><Button disabled={createMutation.isPending}>Guardar borrador</Button><Button type="button" variant="ghost" onClick={() => setCrear(false)}>Cancelar</Button></div>
      </form> : null}

      <section className="rounded-xl border border-border bg-white">
        <div className="flex items-center justify-between border-b border-border px-6 py-4"><div><h2 className="font-semibold text-text">Versiones registradas</h2><p className="mt-1 text-sm text-muted">Una versión publicada solo admite consulta.</p></div><LockKeyhole className="text-muted" size={20} /></div>
        <div className="divide-y divide-border">{versiones.map((version) => <div className="grid gap-4 px-6 py-5 transition hover:bg-slate-50 md:grid-cols-[1.4fr_.8fr_.8fr_auto] md:items-center" key={version.id}>
          <div><div className="flex items-center gap-2"><strong className="text-sm text-text">{version.version}</strong><Badge tone={version.estado === 'vigente' ? 'green' : version.estado === 'borrador' ? 'gold' : 'gray'}>{version.estado.toUpperCase()}</Badge></div><p className="mt-1 text-xs text-muted">{version.acto}</p></div>
          <div className="text-sm"><span className="block text-xs text-muted">Vigencia</span>{version.vigencia_desde ?? 'Inicio no documentado'} → {version.vigencia_hasta ?? 'abierta'}</div>
          <div className="text-sm"><span className="block text-xs text-muted">Contenido</span>{version.fichas} fichas · {version.funciones} funciones</div>
          <div>{version.estado_dominio === 'publicado' ? <span className="flex items-center gap-2 text-xs text-muted"><CheckCircle2 size={16} /> Solo lectura</span> : null}</div>
        </div>)}</div>
      </section>

      {borrador && vigente ? <section className="grid gap-7 rounded-xl border border-border bg-white p-6 lg:grid-cols-[1fr_1fr]">
        <div><p className="text-xs font-semibold uppercase tracking-wider text-govBlue">Preparación de {borrador.version}</p><h2 className="mt-2 text-lg font-semibold">Carga y comparación controladas</h2>
          <input accept=".json,.xlsx" className="mt-5 block w-full text-sm text-muted file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-govBlue" onChange={(e) => setArchivo(e.target.files?.[0] ?? null)} type="file" />
          <div className="mt-4 flex flex-wrap gap-3"><Button disabled={!archivo || importMutation.isPending} icon={<FileDiff size={17} />} onClick={() => importMutation.mutate({ id: borrador.id, dry: true })} variant="secondary">Dry-run</Button><Button disabled={!archivo || importMutation.isPending} icon={<FileUp size={17} />} onClick={() => importMutation.mutate({ id: borrador.id, dry: false })}>Importar</Button><Button icon={<FileDiff size={17} />} onClick={() => diffMutation.mutate({ from: vigente.id, to: borrador.id })} variant="secondary">Comparar</Button></div>
          {diff ? <div className="mt-5 grid grid-cols-2 gap-x-5 gap-y-3 border-t border-border pt-5 text-sm">{Object.entries(diff.conteos).map(([label, value]) => <div className="flex justify-between" key={label}><span className="text-muted">{label.replace('_', ' ')}</span><strong>{value}</strong></div>)}</div> : null}
          <div className="mt-5 flex flex-wrap items-end gap-3"><Input label="Fecha efectiva" type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} /><Button disabled={!diff || planMutation.isPending} onClick={() => planMutation.mutate({ from: vigente.id, to: borrador.id })} variant="secondary">Mapear funcionarios</Button><Button disabled={!fecha || publishMutation.isPending} onClick={() => publishMutation.mutate(borrador.id)}>Publicar</Button></div>
        </div>
        <ol className="border-l border-border pl-6">{pasos.map((paso, index) => <li className="group relative flex min-h-12 items-start gap-3" key={paso}><span className="absolute -left-[31px] grid h-6 w-6 place-items-center rounded-full border border-border bg-white text-xs font-semibold text-govBlue transition group-hover:border-govBlue">{index + 1}</span><span className="pt-0.5 text-sm text-text">{paso}</span>{index < pasos.length - 1 ? <ArrowRight className="ml-auto rotate-90 text-border" size={15} /> : null}</li>)}</ol>
      </section> : <section className="flex items-start gap-4 rounded-xl border border-dashed border-border bg-white p-6"><ScrollText className="mt-0.5 text-govBlue" /><div><h2 className="font-semibold">Próxima actualización</h2><p className="mt-1 text-sm text-muted">Cree un borrador cuando exista un nuevo acto. El Manual 2023 permanecerá intacto.</p></div></section>}
    </div>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return <div><span className="block text-xs text-blue-200">{label}</span><strong className="mt-1 block text-xl">{value.toLocaleString('es-CO')}</strong></div>;
}
