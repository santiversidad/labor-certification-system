import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatCurrency } from '../../../lib/formatters/currency';
import type { RangoSalarial } from '../types/rangoSalarial.types';

const columns: TableColumn<RangoSalarial>[] = [
  { header: 'Cargo ID', accessor: 'cargoId' },
  { header: 'Grado', accessor: 'grado' },
  { header: 'Salario base', accessor: (row) => formatCurrency(row.salarioBase) },
  { header: 'Vigencia desde', accessor: 'vigenciaDesde' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.activo ? 'green' : 'gray'}>{row.activo ? 'Activo' : 'Inactivo'}</Badge> },
];

export function RangosSalarialesTable({ rangos }: { rangos: RangoSalarial[] }) {
  return <Table columns={columns} data={rangos} />;
}
