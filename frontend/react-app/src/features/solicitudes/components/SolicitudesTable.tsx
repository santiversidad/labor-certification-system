import { Link } from 'react-router-dom';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { SolicitudStatusBadge } from './SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../types/solicitud.types';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'Radicado', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.radicado}</Link> },
  { header: 'Tipo', accessor: 'tipo' },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha solicitud', accessor: 'fechaSolicitud' },
];

export function SolicitudesTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
