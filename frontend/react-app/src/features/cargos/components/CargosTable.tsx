import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Cargo } from '../types/cargo.types';

const columns: TableColumn<Cargo>[] = [
  { header: 'Cargo', accessor: 'nombre' },
  { header: 'Grado', accessor: 'grado' },
  { header: 'Dependencia', accessor: 'dependencia' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.activo ? 'green' : 'gray'}>{row.activo ? 'Activo' : 'Inactivo'}</Badge> },
];

export function CargosTable({ cargos }: { cargos: Cargo[] }) {
  return <Table columns={columns} data={cargos} />;
}
