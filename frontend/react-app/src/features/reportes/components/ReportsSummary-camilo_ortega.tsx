import { Card } from '../../../components/ui/Card';
import type { ReporteResumen } from '../types/reporte.types';

export function ReportsSummary({ resumen }: { resumen: ReporteResumen }) {
  const items = [
    ['Total solicitudes', resumen.total_solicitudes],
    ['Aprobadas', resumen.solicitudes_aprobadas],
    ['Certificados generados', resumen.certificados_generados],
    ['Pagos pendientes', resumen.pagos_pendientes],
  ] as const;

  return (
    <div className="grid gap-4 md:grid-cols-4">
      {items.map(([label, value]) => (
        <Card key={label}>
          <p className="text-sm text-muted">{label}</p>
          <p className="mt-2 text-2xl font-semibold text-text">{value}</p>
        </Card>
      ))}
    </div>
  );
}
