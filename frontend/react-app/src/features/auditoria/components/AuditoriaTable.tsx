import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { AuditLog } from '../types/auditoria.types';

const columns: TableColumn<AuditLog>[] = [
  { header: 'Usuario', accessor: (row) => row.user?.name ?? '—' },
  { header: 'Acción', accessor: 'accion' },
  { header: 'Módulo', accessor: 'modelo' },
  { header: 'Descripción', accessor: (row) => row.descripcion ?? '' },
  { header: 'Fecha', accessor: (row) => formatDate(row.created_at) },
];

export function AuditoriaTable({ logs }: { logs: AuditLog[] }) {
  return <Table columns={columns} data={logs} />;
}
