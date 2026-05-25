import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Cargo } from '../types/cargo.types';

const columns: TableColumn<Cargo>[] = [
  { header: 'Codigo', accessor: 'codigo' },
  { header: 'Cargo', accessor: 'denominacion' },
  { header: 'Grado', accessor: 'grado' },
  { header: 'Dependencia', accessor: 'dependencia' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado ? 'green' : 'gray'}>{row.estado ? 'Activo' : 'Inactivo'}</Badge> },
];

export function CargosTable({ cargos }: { cargos: Cargo[] }) {
  return <Table columns={columns} data={cargos} />;
}
