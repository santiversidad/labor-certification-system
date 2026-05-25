import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatCurrency } from '../../../lib/formatters/currency';
import type { RangoSalarial } from '../types/rangoSalarial.types';

const columns: TableColumn<RangoSalarial>[] = [
  { header: 'Codigo', accessor: 'codigo' },
  { header: 'Grado', accessor: 'grado' },
  { header: 'Vigencia', accessor: 'vigencia_anio' },
  { header: 'Salario basico', accessor: (row) => formatCurrency(Number(row.salario_basico)) },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado ? 'green' : 'gray'}>{row.estado ? 'Activo' : 'Inactivo'}</Badge> },
];

export function RangosSalarialesTable({ rangos }: { rangos: RangoSalarial[] }) {
  return <Table columns={columns} data={rangos} />;
}
