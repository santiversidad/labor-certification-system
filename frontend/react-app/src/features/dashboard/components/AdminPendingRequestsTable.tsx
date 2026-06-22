import { Link } from 'react-router-dom';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'N°', accessor: (row) => <Link className="font-medium text-govBlue" to={`/admin/solicitudes/${row.id}`}>{row.id}</Link> },
  { header: 'Tipo', accessor: 'tipo_certificado' },
  { header: 'Funcionario', accessor: (row) => row.funcionario ? `${row.funcionario.nombres} ${row.funcionario.apellidos}` : '—' },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha', accessor: (row) => row.created_at ? formatDate(row.created_at) : '—' },
];

export function AdminPendingRequestsTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
