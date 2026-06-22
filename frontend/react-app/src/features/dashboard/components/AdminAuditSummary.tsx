import { Card } from '../../../components/ui/Card';
import { formatDate } from '../../../lib/formatters/dates';
import type { AuditLog } from '../../auditoria/types/auditoria.types';

export function AdminAuditSummary({ logs }: { logs: AuditLog[] }) {
  return (
    <Card title="Actividad reciente" description="Últimos eventos registrados en auditoría.">
      <div className="space-y-4">
        {logs.slice(0, 3).map((log) => (
          <div className="rounded-md border border-border bg-background p-3" key={log.id}>
            <p className="text-sm font-semibold text-text">{log.modelo}</p>
            <p className="mt-1 text-xs leading-5 text-muted">{log.descripcion ?? ''}</p>
            <p className="mt-2 text-xs text-muted">
              {log.user?.name ?? 'Sistema'} - {formatDate(log.created_at)}
            </p>
          </div>
        ))}
      </div>
    </Card>
  );
}
