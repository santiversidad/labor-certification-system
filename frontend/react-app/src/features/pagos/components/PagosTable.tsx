import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatCurrency } from '../../../lib/formatters/currency';
import type { PagoSoporte } from '../types/pago.types';

type PagosTableProps = {
  pagos: PagoSoporte[];
  onApprove?: (pago: PagoSoporte) => void;
  onReject?: (pago: PagoSoporte) => void;
};

export function PagosTable({ pagos, onApprove, onReject }: PagosTableProps) {
  const columns: TableColumn<PagoSoporte>[] = [
    { header: 'Solicitud', accessor: 'solicitudId' },
    { header: 'Funcionario', accessor: (row) => row.funcionarioNombre ?? 'No registrado' },
    { header: 'Valor', accessor: (row) => formatCurrency(row.valor) },
    { header: 'Soporte', accessor: (row) => row.soporteUrl ? <a className="font-medium text-govBlue" href={row.soporteUrl}>Ver soporte</a> : 'Sin soporte' },
    { header: 'Fecha carga', accessor: (row) => row.fechaCarga ?? 'Sin carga' },
    { header: 'Estado', accessor: (row) => <Badge tone={row.estado === 'aprobado' ? 'green' : row.estado === 'rechazado' ? 'red' : 'gold'}>{row.estado}</Badge> },
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
