import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { PagoSoporte } from '../types/pago.types';

const columns: TableColumn<PagoSoporte>[] = [
  { header: 'Solicitud N°', accessor: 'solicitud_id' },
  { header: 'Archivo', accessor: 'archivo_original_nombre' },
  { header: 'Funcionario', accessor: (row) => row.funcionario ? `${row.funcionario.nombres} ${row.funcionario.apellidos}` : '—' },
  { header: 'Cargado el', accessor: (row) => row.created_at ? formatDate(row.created_at) : '—' },
  {
    header: 'Estado',
    accessor: (row) => (
      <Badge tone={row.estado === 'aprobado' ? 'green' : row.estado === 'rechazado' ? 'red' : 'gold'}>
        {row.estado}
      </Badge>
    ),
  },
];

export function PagosTable({ pagos }: { pagos: PagoSoporte[] }) {
  return <Table columns={columns} data={pagos} />;
}
