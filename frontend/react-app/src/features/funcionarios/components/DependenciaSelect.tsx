import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import type { Ref } from 'react';

export function DependenciaSelect({ label, value, search, onSearch, onChange, options, placeholder, empty, loading, disabled, error, retry, selectRef, describedBy }: {
  label: string; value: string; search: string; onSearch: (value: string) => void; onChange: (value: string) => void;
  options: { id: string; label: string }[]; placeholder: string; empty: string;
  loading?: boolean; disabled?: boolean; error?: string; retry?: () => void;
  selectRef?: Ref<HTMLSelectElement>; describedBy?: string;
}) {
  return <div className="min-w-0 space-y-2">
    <Select ref={selectRef} {...(describedBy ? { 'aria-describedby': describedBy } : {})} label={label} value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled || loading} error={error}>
      <option value="">{loading ? `Cargando ${label === 'Cargo' ? 'cargos' : 'dependencias'}...` : placeholder}</option>
      {options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
    </Select>
    <Input label={label === 'Cargo' ? 'Buscar cargo por nombre, código o grado' : 'Buscar dependencia por nombre'} type="search" value={search} onChange={(event) => onSearch(event.target.value)} disabled={disabled} />
    {!disabled && !loading && !error && !options.length && <p className="text-sm text-muted" role="status">{empty}</p>}
    {loading && <p className="text-sm text-muted" role="status">Cargando {label === 'Cargo' ? 'cargos' : 'dependencias'}...</p>}
    {error && retry && <button className="text-sm font-semibold text-primary underline" type="button" onClick={retry}>Reintentar carga de {label.toLowerCase()}</button>}
  </div>;
}
