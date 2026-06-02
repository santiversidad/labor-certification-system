import { Link } from 'react-router-dom';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'Radicado', accessor: (row) => <Link className="font-medium text-govBlue" to={`/admin/solicitudes/${row.id}`}>{row.radicado}</Link> },
  { header: 'Tipo', accessor: 'tipo' },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha', accessor: 'fechaSolicitud' },
];

export function AdminPendingRequestsTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
