import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Funcionario } from '../types/funcionario.types';

const columns: TableColumn<Funcionario>[] = [
  { header: 'Nombre', accessor: (row) => <Link className="font-medium text-govBlue" to={`${row.id}`}>{row.nombres} {row.apellidos}</Link> },
  { header: 'Documento', accessor: 'documento' },
  { header: 'Dependencia', accessor: 'dependencia' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado === 'activo' ? 'green' : 'gray'}>{row.estado}</Badge> },
];

export function FuncionariosTable({ funcionarios }: { funcionarios: Funcionario[] }) {
  return <Table columns={columns} data={funcionarios} />;
}
