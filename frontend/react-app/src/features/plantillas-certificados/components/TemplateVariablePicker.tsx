import { useState } from 'react';
import { Modal } from '../../../components/ui/Modal';
import { Input } from '../../../components/ui/Input';
import type { Variable } from '../types/template.types';

export function TemplateVariablePicker({ variables, onSelect, onClose }: { variables: Variable[]; onSelect: (variable: Variable) => void; onClose: () => void }) {
  const [search, setSearch] = useState('');
  const filtered = variables.filter((variable) => variable.etiqueta.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
  return <Modal open onClose={onClose} title="Insertar variable"><Input label="Buscar variable" value={search} onChange={(e) => setSearch(e.target.value)} />
    <div className="mt-4 space-y-5">{[...new Set(filtered.map((v) => v.grupo))].map((group) => <section key={group}><h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-muted">{group}</h3><div className="space-y-1">{filtered.filter((v) => v.grupo === group).map((variable) => <button type="button" key={variable.id} onClick={() => onSelect(variable)} className="block w-full rounded-md px-3 py-2 text-left transition hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-primary"><span className="block text-sm font-semibold">{variable.etiqueta}</span><span className="text-xs text-muted">{variable.descripcion}{variable.ausencia === 'omitir_parrafo' ? ' Si no hay dato, se omite el párrafo.' : ''}</span></button>)}</div></section>)}{!filtered.length && <p className="text-sm">No hay variables que coincidan.</p>}</div>
  </Modal>;
}
