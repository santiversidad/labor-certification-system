import { Badge } from '../../../components/ui/Badge';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatCurrency } from '../../../lib/formatters/currency';
import type { PagoSoporte } from '../types/pago.types';

const columns: TableColumn<PagoSoporte>[] = [
  { header: 'Solicitud', accessor: 'solicitudId' },
  { header: 'Valor', accessor: (row) => formatCurrency(row.valor) },
  { header: 'Referencia', accessor: (row) => row.referencia ?? 'Sin referencia' },
  { header: 'Estado', accessor: (row) => <Badge tone={row.estado === 'aprobado' ? 'green' : row.estado === 'rechazado' ? 'red' : 'gold'}>{row.estado}</Badge> },
];

export function PagosTable({ pagos }: { pagos: PagoSoporte[] }) {
  return <Table columns={columns} data={pagos} />;
}
