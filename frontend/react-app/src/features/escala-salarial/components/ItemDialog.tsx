import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Modal } from '../../../components/ui/Modal';
import { countSchema, salarySchema, salaryError } from '../schemas/salary-scale.schema';
import { salaryScaleService } from '../services/salary-scale.service';
import type { SalaryItem } from '../types/salary-scale.types';

export function ItemDialog({ item, year, onClose, onSaved }: { item: SalaryItem; year: number; onClose: () => void; onSaved: () => void }) {
  const [value, setValue] = useState(item.asignacion_basica_mensual ?? '');
  const [count, setCount] = useState(item.numero_cargos?.toString() ?? '');
  const save = useMutation({ mutationFn: () => salaryScaleService.edit(item, value || null, count ? Number(count) : null), onSuccess: onSaved });
  const valid = (!value || salarySchema.safeParse(value).success) && (!count || countSchema.safeParse(count).success);
  return <Modal open title={`Editar valor · ${year}`} onClose={() => { if (!save.isPending) onClose(); }}><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); if (valid) save.mutate(); }}>
    <p className="text-sm">{item.denominacion} · Código {item.codigo} · Grado {item.grado}</p>
    <Input label="Asignación básica mensual (COP)" inputMode="numeric" value={value} onChange={(event) => setValue(event.target.value)} />
    <p className="text-xs text-muted">Pesos enteros sin separadores. Deje vacío para marcar pendiente.</p>
    <Input label="Número de cargos en esta vigencia" inputMode="numeric" value={count} onChange={(event) => setCount(event.target.value)} />
    <p className="text-xs text-muted">Este cambio solo afecta el borrador {year}.</p>
    {save.error && <p className="text-sm text-error" role="alert">{salaryError(save.error)}</p>}
    <div className="flex justify-end gap-2"><Button variant="secondary" type="button" disabled={save.isPending} onClick={onClose}>Cancelar</Button><Button type="submit" disabled={!valid || save.isPending}>Guardar valor</Button></div>
  </form></Modal>;
}
