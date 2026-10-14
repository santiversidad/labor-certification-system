import { useRef, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { Select } from '../../../components/ui/Select';
import { TemplateVariablePicker } from './TemplateVariablePicker';
import { displayRuns, parseRuns } from '../schemas/template.schema';
import type { Alignment, Paragraph, Variable } from '../types/template.types';

export function TemplateBlockEditor({ paragraph, variables, index, count, disabled, onChange, onMove, onDelete }: { paragraph: Paragraph; variables: Variable[]; index: number; count: number; disabled: boolean; onChange: (paragraph: Paragraph) => void; onMove: (direction: -1 | 1) => void; onDelete: () => void }) {
  const ref = useRef<HTMLTextAreaElement>(null); const selection = useRef({ start: 0, end: 0 }); const [picker, setPicker] = useState(false);
  const text = displayRuns(paragraph.runs, variables); const bold = paragraph.runs.every((r) => r.bold); const italic = paragraph.runs.every((r) => r.italic);
  const mandatory = ['certificacion', 'cierre'].includes(paragraph.id);
  function change(value: string, b = bold, i = italic) { onChange({ ...paragraph, runs: parseRuns(value, variables, b, i) }); }
  function insert(variable: Variable) {
    const marker = `[${variable.etiqueta}]`; const { start, end } = selection.current;
    change(text.slice(0, start) + marker + text.slice(end)); setPicker(false);
    requestAnimationFrame(() => { ref.current?.focus(); ref.current?.setSelectionRange(start + marker.length, start + marker.length); });
  }
  return <section className="space-y-3 border-b border-border py-4" aria-label={`Párrafo ${index + 1}`}>
    <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="text-sm font-semibold">Párrafo {index + 1}{mandatory && <span className="ml-2 text-xs font-normal text-muted">Obligatorio</span>}</h3><div className="flex gap-1"><Button type="button" variant="ghost" disabled={disabled || index === 0} aria-label={`Subir párrafo ${index + 1}`} onClick={() => onMove(-1)}>↑</Button><Button type="button" variant="ghost" disabled={disabled || index === count - 1} aria-label={`Bajar párrafo ${index + 1}`} onClick={() => onMove(1)}>↓</Button>{!mandatory && <Button type="button" variant="ghost" disabled={disabled} onClick={onDelete} aria-label={`Eliminar párrafo ${index + 1}`}>Eliminar</Button>}</div></div>
    <label className="block text-sm font-semibold" htmlFor={`paragraph-${paragraph.id}`}>Texto del párrafo {index + 1}</label><textarea id={`paragraph-${paragraph.id}`} ref={ref} className="field-control min-h-28 w-full resize-y" value={text} maxLength={12000} disabled={disabled} onChange={(e) => change(e.target.value)} />
    <div className="flex flex-wrap items-center gap-3"><Button type="button" variant="secondary" disabled={disabled} onClick={() => { selection.current = { start: ref.current?.selectionStart ?? text.length, end: ref.current?.selectionEnd ?? text.length }; setPicker(true); }}>Insertar variable</Button><label className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={disabled} checked={Boolean(bold)} onChange={(e) => change(text, e.target.checked, italic)} />Negrita</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={disabled} checked={Boolean(italic)} onChange={(e) => change(text, bold, e.target.checked)} />Cursiva</label></div>
    <div className="grid gap-3 sm:grid-cols-2"><Select label={`Sección del párrafo ${index + 1}`} value={paragraph.position} disabled={disabled || mandatory} onChange={(e) => onChange({ ...paragraph, position: e.target.value as Paragraph['position'] })}><option value="body">Contenido laboral</option><option value="closing">Cierre</option></Select><Select label={`Alineación del párrafo ${index + 1}`} value={paragraph.alignment} disabled={disabled} onChange={(e) => onChange({ ...paragraph, alignment: e.target.value as Alignment })}><option value="left">Izquierda</option><option value="justify">Justificada</option><option value="center">Centrada</option><option value="right">Derecha</option></Select></div>
    {picker && <TemplateVariablePicker variables={variables} onSelect={insert} onClose={() => setPicker(false)} />}
  </section>;
}
