import type { AuditLog } from '../../features/auditoria/types/auditoria.types';

export const mockAuditoria: AuditLog[] = [
  {
    id: 'aud-001',
    userId: 'usr-003',
    userName: 'Administrador Demo',
    action: 'actualizar',
    module: 'Funcionarios',
    description: 'Actualizo datos laborales de Laura Marcela Gomez.',
    createdAt: '2026-05-20T14:30:00-05:00',
  },
  {
    id: 'aud-002',
    userId: 'usr-002',
    userName: 'Secretario Demo',
    action: 'validar',
    module: 'Pagos',
    description: 'Valido soporte de pago REC-000198.',
    createdAt: '2026-05-21T09:15:00-05:00',
  },
];
