import type { ReporteResumen } from '../types/reporte.types';

export function ReportsSummary({ resumen }: { resumen: ReporteResumen }) {
  const items = [
    ['Funcionarios', resumen.total_funcionarios],
    ['Solicitudes', resumen.total_solicitudes],
    ['Certificados generados', resumen.certificados_generados],
    ['Pagos pendientes', resumen.pagos_pendientes],
  ] as const;
  const distribution = [
    ['Pendientes', resumen.solicitudes_pendientes, 'bg-warning'],
    ['Expedidas / históricamente aprobadas', resumen.solicitudes_aprobadas, 'bg-success'],
    ['Rechazadas históricas', resumen.solicitudes_rechazadas, 'bg-error'],
  ] as const;
  const max = Math.max(...distribution.map(([, value]) => value), 1);

  return <div className="space-y-6">
    <section className="grid overflow-hidden rounded-lg border border-border bg-surface shadow-soft sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de reportes">
      {items.map(([label, value], index) => <div className={`p-5 ${index ? 'border-t border-border sm:border-l sm:border-t-0' : ''}`} key={label}><p className="text-sm font-semibold text-muted">{label}</p><p className="mt-3 text-3xl font-bold tracking-tight text-text">{value.toLocaleString('es-CO')}</p></div>)}
    </section>
    <section className="surface-section p-5 sm:p-6"><div className="mb-6"><h2 className="font-bold text-text">Distribución de solicitudes</h2><p className="mt-1 text-sm text-muted">Conteos acumulados entregados por el servicio de reportes.</p></div><div className="space-y-5">{distribution.map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between gap-4 text-sm"><span className="font-medium text-text">{label}</span><strong>{value.toLocaleString('es-CO')}</strong></div><div className="h-2.5 overflow-hidden rounded-full bg-surface-muted"><div aria-label={`${label}: ${value}`} className={`h-full rounded-full ${color}`} style={{ width: `${Math.max((value / max) * 100, value ? 4 : 0)}%` }} /></div></div>)}</div></section>
    <section className="surface-section p-5 sm:p-6"><p className="text-sm font-semibold text-muted">Tiempo promedio de respuesta histórico</p><p className="mt-2 text-2xl font-bold text-text">{resumen.tiempo_promedio_respuesta === null ? 'No disponible' : `${resumen.tiempo_promedio_respuesta.toLocaleString('es-CO')} días`}</p></section>
  </div>;
}
