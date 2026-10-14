import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { Modal } from '../../../components/ui/Modal';
import { cop, percentageSchema, salaryError } from '../schemas/salary-scale.schema';
import { salaryScaleService } from '../services/salary-scale.service';
import type { IncreasePreview, SalaryYear } from '../types/salary-scale.types';

export function YearDialog({ years, target, onClose, onSaved }: { years: SalaryYear[]; target?: SalaryYear; onClose: () => void; onSaved: () => void }) {
  const [year, setYear] = useState(String(target?.anio ?? Math.max(new Date().getFullYear(), ...years.map((item) => item.anio + 1))));
  const [baseId, setBaseId] = useState(String(target?.vigencia_base_id ?? years.at(-1)?.id ?? ''));
  const [percentage, setPercentage] = useState('');
  const [source, setSource] = useState(target?.fuente ?? '');
  const [preview, setPreview] = useState<IncreasePreview | null>(null);
  const base = years.find((item) => item.id === Number(baseId));
  const peek = useMutation({ mutationFn: () => salaryScaleService.preview({ anio: Number(year), vigencia_base_id: Number(baseId), porcentaje: percentage }), onSuccess: setPreview });
  const save = useMutation({ mutationFn: () => {
    const payload = { vigencia_base_id: base?.id, base_revision: preview?.base_revision ?? base?.revision, ...(percentage ? { porcentaje: percentage, confirmado: true } : {}) };
    return target ? salaryScaleService.calculate(target, payload) : salaryScaleService.create({ anio: Number(year), fuente: source, ...payload });
  }, onSuccess: onSaved });
  const valid = /^\d{4}$/.test(year) && Number(year) >= 1900 && Number(year) <= 2199 && Boolean(source.trim()) && (!percentage || (Boolean(base) && percentageSchema.safeParse(percentage).success)) && (!target || Boolean(percentage));
  const busy = peek.isPending || save.isPending;
  function changed() { setPreview(null); peek.reset(); save.reset(); }
  return <Modal open title={target ? `Aplicar aumento · ${target.anio}` : 'Crear vigencia'} onClose={() => { if (!busy) onClose(); }}>
    <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); if (!valid || busy) return; if (percentage && !preview) peek.mutate(); else save.mutate(); }}>
      <p className="text-sm text-muted">La nueva vigencia se crea en borrador. Un cálculo porcentual requiere revisión y no acredita valores oficiales.</p>
      <Input label="Año nuevo" inputMode="numeric" value={year} disabled={Boolean(target) || busy} onChange={(event) => { setYear(event.target.value); changed(); }} />
      <Select label="Vigencia base" value={baseId} disabled={busy} onChange={(event) => { setBaseId(event.target.value); changed(); }}>
        <option value="">Sin base: estructura del catálogo de cargos</option>
        {years.filter((item) => item.anio < Number(year)).map((item) => <option key={item.id} value={item.id}>{item.anio} · {item.estado}</option>)}
      </Select>
      {!target && <Input label="Fuente / procedencia" maxLength={250} required value={source} disabled={busy} onChange={(event) => { setSource(event.target.value); changed(); }} />}
      <Input label={target ? 'Porcentaje de aumento (%)' : 'Porcentaje de aumento (%) · opcional'} inputMode="decimal" value={percentage} disabled={busy} onChange={(event) => { setPercentage(event.target.value); changed(); }} />
      <p className="text-xs text-muted">Sin porcentaje, los valores quedan pendientes. Redondeo al peso entero: las mitades se redondean hacia arriba. Límite preventivo de captura: 0 a 100 %; no es un límite legal.</p>
      {preview && <div className="rounded-md border border-border bg-surface-muted p-3" role="status">
        <h3 className="font-semibold">Vista previa · {preview.cantidad} filas</h3>
        <p className="text-sm">Base {base?.anio} → {year} · {percentage} %</p>
        <ul className="mt-3 space-y-2 text-sm">{preview.ejemplos.map((item) => <li key={`${item.codigo}-${item.grado}-${item.denominacion}`}><span className="block">{item.denominacion} · {item.codigo} / {item.grado}</span><strong>{cop(item.valor_base)} → {cop(item.valor_nuevo)}</strong></li>)}</ul>
        <p className="mt-3 text-xs text-muted">Valores calculados por el servidor. Confirme para generar todo el borrador.</p>
      </div>}
      {(peek.error || save.error) && <p role="alert" className="text-sm text-error">{salaryError(peek.error ?? save.error)}</p>}
      <div className="flex flex-wrap justify-end gap-2"><Button type="button" variant="secondary" disabled={busy} onClick={onClose}>Cancelar</Button><Button disabled={!valid || busy} type="submit">{busy ? 'Procesando…' : percentage ? preview ? 'Confirmar generación del borrador' : 'Previsualizar aumento' : 'Crear borrador'}</Button></div>
    </form>
  </Modal>;
}
