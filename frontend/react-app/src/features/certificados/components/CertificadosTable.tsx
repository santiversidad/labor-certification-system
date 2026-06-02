import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Certificado } from '../types/certificado.types';

const columns: TableColumn<Certificado>[] = [
  { header: 'Codigo', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.codigoValidacion}</Link> },
  { header: 'Solicitud', accessor: 'solicitudId' },
  { header: 'Generado', accessor: 'fechaGeneracion' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado === 'anulado' || row.estado === 'vencido' ? 'red' : 'green'}>{row.estado}</Badge> },
];

export function CertificadosTable({ certificados }: { certificados: Certificado[] }) {
  return <Table columns={columns} data={certificados} />;
}
