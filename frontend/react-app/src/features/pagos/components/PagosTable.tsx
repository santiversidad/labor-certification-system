import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { PagoSoporte } from '../types/pago.types';

type PagosTableProps = {
  pagos: PagoSoporte[];
  onApprove?: (pago: PagoSoporte) => void;
  onReject?: (pago: PagoSoporte) => void;
};

export function PagosTable({ pagos, onApprove, onReject }: PagosTableProps) {
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
    {
      header: 'Acciones',
      accessor: (row) => (
        <div className="flex gap-2">
          <Button disabled={row.estado === 'aprobado'} onClick={() => onApprove?.(row)} type="button" variant="secondary">Aprobar</Button>
          <Button disabled={row.estado === 'rechazado'} onClick={() => onReject?.(row)} type="button" variant="danger">Rechazar</Button>
        </div>
      ),
    },
  ];

  return <Table columns={columns} data={pagos} />;
}
