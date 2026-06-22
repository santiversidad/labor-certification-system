import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { Certificado } from '../types/certificado.types';

const columns: TableColumn<Certificado>[] = [
  { header: 'Código', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.codigo_unico}</Link> },
  { header: 'Generado', accessor: (row) => row.fecha_generacion ? formatDate(row.fecha_generacion) : '—' },
  {
    header: 'Estado',
    accessor: (row) => (
      <Badge tone={row.estado === 'anulado' ? 'red' : 'green'}>{row.estado}</Badge>
    ),
  },
];

export function CertificadosTable({ certificados }: { certificados: Certificado[] }) {
  return <Table columns={columns} data={certificados} />;
}
