import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Modal } from '../../../components/ui/Modal';
import { salaryError } from '../schemas/salary-scale.schema';
import { salaryScaleService } from '../services/salary-scale.service';
import type { SalaryYear } from '../types/salary-scale.types';

export function YearDetailsDialog({ year, publish, onClose, onSaved }: { year: SalaryYear; publish?: boolean; onClose: () => void; onSaved: () => void }) {
  const [source, setSource] = useState(year.fuente);
  const [act, setAct] = useState(year.acto_administrativo ?? '');
  const [date, setDate] = useState(year.fecha_vigencia ?? '');
  const [notes, setNotes] = useState(year.observaciones ?? '');
  const save = useMutation({ mutationFn: () => publish ? salaryScaleService.publish(year) : salaryScaleService.metadata(year, { fuente: source, acto_administrativo: act || null, fecha_vigencia: date || null, observaciones: notes || null }), onSuccess: onSaved });
  return <Modal open title={`${publish ? 'Publicar vigencia' : 'Procedencia'} · ${year.anio}`} onClose={() => { if (!save.isPending) onClose(); }}><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); save.mutate(); }}>
    {publish ? <>
      <p className="text-sm">Confirme que revisó todas las asignaciones básicas. Los valores quedarán de solo lectura y se conservarán como referencia operativa.</p>
      <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">{year.acto_administrativo ? 'El acto registrado es metadata de procedencia.' : 'Acto administrativo pendiente de registrar.'} Publicar en el sistema no acredita un decreto ni convierte un cálculo en oficial.</p>
    </> : <>
      <Input label="Fuente / procedencia" value={source} maxLength={250} required onChange={(event) => setSource(event.target.value)} />
      <Input label="Acto administrativo · opcional" value={act} maxLength={250} onChange={(event) => setAct(event.target.value)} />
      <Input label="Fecha de vigencia · opcional" value={date} type="date" onChange={(event) => setDate(event.target.value)} />
      <Input label="Observaciones · opcional" value={notes} maxLength={2000} onChange={(event) => setNotes(event.target.value)} />
      <p className="text-xs text-muted">La procedencia puede completarse después de publicar. Los valores históricos permanecen protegidos.</p>
    </>}
    {save.error && <p className="text-sm text-error" role="alert">{salaryError(save.error)}</p>}
    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" disabled={save.isPending} onClick={onClose}>Cancelar</Button><Button type="submit" disabled={save.isPending || !source.trim()}>{publish ? 'Confirmar publicación' : 'Guardar procedencia'}</Button></div>
  </form></Modal>;
}
