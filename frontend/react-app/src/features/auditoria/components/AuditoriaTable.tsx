import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { AuditLog } from '../types/auditoria.types';

const columns: TableColumn<AuditLog>[] = [
  { header: 'Usuario', accessor: 'userName' },
  { header: 'Accion', accessor: 'action' },
  { header: 'Entidad afectada', accessor: (row) => row.entity ?? row.module },
  { header: 'IP', accessor: (row) => row.ip ?? 'No disponible' },
  { header: 'Descripcion', accessor: 'description' },
  { header: 'Fecha', accessor: (row) => formatDate(row.createdAt) },
];

export function AuditoriaTable({ logs }: { logs: AuditLog[] }) {
  return <Table columns={columns} data={logs} />;
}
