import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Certificado } from '../types/certificado.types';
import { canDownloadCertificado } from '../utils/certificadoRules';

const columns: TableColumn<Certificado>[] = [
  { header: 'Codigo', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.codigoValidacion}</Link> },
  { header: 'Solicitud', accessor: (row) => row.solicitudRadicado ?? row.solicitudId },
  { header: 'Funcionario', accessor: (row) => row.funcionarioNombre ?? 'No registrado' },
  { header: 'Generado', accessor: 'fechaGeneracion' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado === 'anulado' || row.estado === 'vencido' ? 'red' : 'green'}>{row.estado}</Badge> },
  {
    header: 'Descarga',
    accessor: (row) => canDownloadCertificado(row)
      ? <a href={row.descargaUrl}><Button type="button" variant="secondary">Descargar</Button></a>
      : <Button disabled type="button" variant="secondary">No disponible</Button>,
  },
];

export function CertificadosTable({ certificados }: { certificados: Certificado[] }) {
  return <Table columns={columns} data={certificados} />;
}
