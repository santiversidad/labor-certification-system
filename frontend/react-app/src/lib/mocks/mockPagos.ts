import type { PagoSoporte } from '../../features/pagos/types/pago.types';

export const mockPagos: PagoSoporte[] = [
  {
    id: 'pag-001',
    solicitudId: 'sol-001',
    funcionarioNombre: 'Funcionario Demo',
    valor: 14800,
    estado: 'pendiente',
    referencia: 'REC-000234',
  },
  {
    id: 'pag-002',
    solicitudId: 'sol-002',
    funcionarioNombre: 'Secretario Demo',
    valor: 14800,
    estado: 'aprobado',
    fechaCarga: '2026-05-12',
    referencia: 'REC-000198',
    soporteUrl: '#',
  },
];
