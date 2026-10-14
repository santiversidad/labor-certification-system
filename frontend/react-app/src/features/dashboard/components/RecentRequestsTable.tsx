import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';
import { CERTIFICATE_TYPE_SHORT_LABELS } from '../../solicitudes/utils/certificateType';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'N°', accessor: 'id' },
  { header: 'Tipo', accessor: (row) => CERTIFICATE_TYPE_SHORT_LABELS[row.tipo_certificado] },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha', accessor: (row) => row.created_at ? formatDate(row.created_at) : '—' },
];

export function RecentRequestsTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
