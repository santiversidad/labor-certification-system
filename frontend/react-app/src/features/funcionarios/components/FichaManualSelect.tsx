export type FichaManual = {
  id: number; source_id: string | null; denominacion: string; codigo: string; grado: string;
  dependencia: string | null; area_funcional: string | null; proposito_principal: string;
  version: string; estado: string; vigente: boolean; funciones_count?: number;
};

export function FichaManualSelect({ fichas, value, onChange, loading, error }: {
  fichas: FichaManual[]; value: string; onChange: (value: string) => void; loading?: boolean; error?: string;
}) {
  const selected = fichas.find((f) => String(f.id) === value);
  return <div className="space-y-3">
    <label className="block space-y-1.5 text-sm">
      <span className="font-semibold text-text">Ficha del Manual de Funciones</span>
      <select aria-describedby={error ? 'ficha-manual-error' : 'ficha-manual-hint'} aria-invalid={Boolean(error)} className="field-control" id="ficha-manual" value={value} onChange={(e) => onChange(e.target.value)} disabled={loading}>
        <option value="">{loading ? 'Cargando fichas…' : 'Seleccione la ficha exacta…'}</option>
        {fichas.map((f) => <option key={f.id} value={f.id} disabled={f.estado === 'inactivo'}>
          {f.denominacion} — {f.codigo}-{f.grado} — {f.area_funcional || 'Área pendiente'} — {f.dependencia} — {f.source_id || f.id} ({f.estado})
        </option>)}
      </select>
    </label>
    {!loading && !fichas.length && <p className="text-sm text-muted" id="ficha-manual-hint">No hay fichas para este cargo. Importe y revise el Manual antes de vincularlo.</p>}
    {fichas.length > 1 && <p className="text-sm text-muted" id="ficha-manual-hint">Hay varias fichas compatibles. Revise área, dependencia y propósito antes de escoger.</p>}
    {selected && <div className="rounded-lg border border-border bg-surface-muted/55 p-4 text-sm"><div className="grid gap-3 sm:grid-cols-2"><p><span className="block text-xs text-muted">Denominación</span><strong className="text-text">{selected.denominacion}</strong></p><p><span className="block text-xs text-muted">Código / grado</span><strong className="text-text">{selected.codigo} / {selected.grado}</strong></p><p><span className="block text-xs text-muted">Dependencia</span><strong className="text-text">{selected.dependencia || 'No informada'}</strong></p><p><span className="block text-xs text-muted">Área</span><strong className="text-text">{selected.area_funcional || 'No informada'}</strong></p><p><span className="block text-xs text-muted">Source ID</span><strong className="text-text">{selected.source_id || selected.id}</strong></p>{selected.funciones_count !== undefined ? <p><span className="block text-xs text-muted">Funciones</span><strong className="text-text">{selected.funciones_count}</strong></p> : null}</div><p className="mt-4 border-t border-border pt-3 leading-6 text-muted">{selected.version}. {selected.proposito_principal || 'Propósito pendiente.'} {!selected.vigente && 'Esta ficha aún no habilita la expedición ordinaria.'}</p></div>}
    {error && <p className="text-sm text-error" id="ficha-manual-error" role="alert">{error}</p>}
  </div>;
}
