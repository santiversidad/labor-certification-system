import { Button } from '../../../components/ui/Button';
import { cop } from '../schemas/salary-scale.schema';
import type { SalaryItem, SalaryTable as TableData, SalaryYear } from '../types/salary-scale.types';

export function SalaryTable({ data, onEdit, onIncrease, onPublish, onDetails }: { data: TableData; onEdit: (item: SalaryItem, year: SalaryYear) => void; onIncrease: (year: SalaryYear) => void; onPublish: (year: SalaryYear) => void; onDetails: (year: SalaryYear) => void }) {
  return <div role="region" aria-label="Tabla de escala salarial; desplace horizontalmente para ver todas las vigencias" tabIndex={0} className="max-w-full overflow-x-auto rounded-md border border-border focus:outline-primary">
    <table className="w-full min-w-[780px] border-collapse text-left text-sm">
      <caption className="sr-only">Asignación básica mensual por cargo y vigencia. Número de cargos conservado en cada año.</caption>
      <thead className="bg-surface-muted"><tr><th className="p-3">Código</th><th className="p-3">Grado</th><th className="min-w-60 p-3">Denominación</th>{data.vigencias.map((year) => <th className="min-w-64 p-3 align-top font-normal" key={year.id}>
        <div className="flex items-center gap-2"><strong className="text-base">{year.anio}</strong><span className={`rounded px-2 py-0.5 text-xs font-semibold ${year.estado === 'borrador' ? 'bg-amber-100 text-amber-900' : 'bg-slate-200 text-slate-700'}`}>{year.estado}</span></div>
        <p className="mt-1 text-xs">Asignación básica mensual · COP</p>
        {!year.acto_administrativo && <p className="mt-1 text-xs text-amber-800">Acto pendiente de registrar</p>}
        {year.porcentaje_referencia !== null && <p className="mt-1 text-xs">Referencia: {year.porcentaje_referencia} % · base {data.vigencias.find((base) => base.id === year.vigencia_base_id)?.anio}</p>}
        <div className="mt-2 flex flex-wrap gap-1"><Button type="button" variant="ghost" onClick={() => onDetails(year)} aria-label={`Procedencia ${year.anio}`}>Procedencia</Button>{year.estado === 'borrador' && <><Button type="button" variant="ghost" onClick={() => onIncrease(year)} aria-label={`Aplicar aumento ${year.anio}`}>Aplicar aumento</Button><Button type="button" variant="secondary" onClick={() => onPublish(year)} aria-label={`Publicar ${year.anio}`}>Publicar</Button></>}</div>
      </th>)}</tr></thead>
      <tbody>{data.filas.map((row) => <tr key={`${row.codigo}-${row.grado}-${row.denominacion}`} className="border-t border-border hover:bg-surface-muted/60"><td className="p-3 font-mono">{row.codigo}</td><td className="p-3 font-mono">{row.grado}</td><th scope="row" className="p-3 font-medium">{row.denominacion}</th>{data.vigencias.map((year) => {
        const item = row.valores.find((cell) => cell.vigencia_id === year.id);
        return <td className="p-3 align-top" key={year.id}>{item ? <>
          <span className="block whitespace-nowrap font-semibold tabular-nums">{cop(item.asignacion_basica_mensual)}</span>
          <span className="mt-1 block text-xs text-muted">N.º cargos: {item.numero_cargos ?? 'Pendiente'}</span>
          <span className="block text-xs text-muted">{item.origen_valor === 'calculado_por_porcentaje' ? 'Calculado por porcentaje' : item.origen_valor === 'importado' ? 'Fuente importada' : item.origen_valor === 'manual' ? 'Editado manualmente' : 'Sin valor registrado'}</span>
          {item.cargo_id === null && <span className="block text-xs text-amber-800">Sin vinculación al catálogo</span>}
          {year.estado === 'borrador' && <Button type="button" variant="ghost" onClick={() => onEdit(item, year)} aria-label={`Editar ${row.codigo} grado ${row.grado} ${year.anio}`}>Editar valor</Button>}
        </> : <span className="text-muted">Sin fila en esta vigencia</span>}</td>;
      })}</tr>)}</tbody>
    </table>
  </div>;
}
