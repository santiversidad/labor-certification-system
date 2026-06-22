import { Link } from 'react-router-dom';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from './SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../types/solicitud.types';

const tipoLabels: Record<string, string> = {
  laboral: 'Laboral',
  funciones: 'Funciones',
  salario: 'Salario',
  laboral_salario: 'Laboral con salario',
};

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'N°', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.id}</Link> },
  { header: 'Tipo', accessor: (row) => tipoLabels[row.tipo_certificado] ?? row.tipo_certificado },
  { header: 'Solicitante', accessor: (row) => row.funcionario ? `${row.funcionario.nombres} ${row.funcionario.apellidos}` : '—' },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha solicitud', accessor: (row) => row.created_at ? formatDate(row.created_at) : '—' },
];

export function SolicitudesTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
