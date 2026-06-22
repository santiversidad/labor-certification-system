import { Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { SolicitudStatusBadge } from './SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../types/solicitud.types';

const columns: TableColumn<SolicitudCertificacion>[] = [
  { header: 'Radicado', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.radicado}</Link> },
  { header: 'Funcionario', accessor: (row) => row.funcionarioNombre ?? row.funcionarioId },
  { header: 'Documento', accessor: (row) => row.funcionarioDocumento ?? 'No registrado' },
  { header: 'Tipo', accessor: 'tipo' },
  { header: 'Requiere salario', accessor: (row) => (row.incluyeSalario ? 'Si' : 'No') },
  { header: 'Estado', accessor: (row) => <SolicitudStatusBadge estado={row.estado} /> },
  { header: 'Fecha', accessor: 'fechaSolicitud' },
  { header: 'Accion', accessor: (row) => <Link to={`${row.id}`}><Button type="button" variant="secondary">Revisar</Button></Link> },
];

export function SolicitudesTable({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return <Table columns={columns} data={solicitudes} />;
}
