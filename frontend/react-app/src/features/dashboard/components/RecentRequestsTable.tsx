import { Table, type TableColumn } from '../../../components/ui/Table';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'Radicado', accessor: 'radicado' },
  { header: 'Tipo', accessor: 'tipo' },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha', accessor: 'fechaSolicitud' },
];

export function RecentRequestsTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
