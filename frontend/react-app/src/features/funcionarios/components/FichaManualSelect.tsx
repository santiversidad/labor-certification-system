export type FichaManual = {
  id: number; source_id: string | null; denominacion: string; codigo: string; grado: string;
  dependencia: string | null; area_funcional: string | null; proposito_principal: string;
  version: string; estado: string; vigente: boolean;
};

export function FichaManualSelect({ fichas, value, onChange, loading, error }: {
  fichas: FichaManual[]; value: string; onChange: (value: string) => void; loading?: boolean; error?: string;
}) {
  const selected = fichas.find((f) => String(f.id) === value);
  return <div className="space-y-2 md:col-span-2">
    <label className="block space-y-1 text-sm">
      <span className="font-medium">Ficha del Manual de Funciones</span>
      <select className="w-full rounded-md border border-border px-3 py-2.5" value={value} onChange={(e) => onChange(e.target.value)} disabled={loading}>
        <option value="">{loading ? 'Cargando fichas…' : 'Seleccione la ficha exacta…'}</option>
        {fichas.map((f) => <option key={f.id} value={f.id} disabled={f.estado === 'inactivo'}>
          {f.denominacion} — {f.codigo}-{f.grado} — {f.area_funcional || 'Área pendiente'} — {f.dependencia} — {f.source_id || f.id} ({f.estado})
        </option>)}
      </select>
    </label>
    {!loading && !fichas.length && <p className="text-sm text-muted">No hay fichas para este cargo. Importe y revise el Manual antes de vincularlo.</p>}
    {fichas.length > 1 && <p className="text-sm text-muted">Hay varias fichas. Revise el área y el propósito antes de escoger.</p>}
    {selected && <p className="text-sm text-muted">{selected.version}. {selected.proposito_principal || 'Propósito pendiente.'} {!selected.vigente && 'Esta ficha aún no habilita la expedición ordinaria.'}</p>}
    {error && <p className="text-sm text-villavoRed" role="alert">{error}</p>}
  </div>;
}
