import type { User } from '../../types/common.types';

export const mockUsers: User[] = [
  {
    id: 'usr-001',
    name: 'Funcionario Demo',
    email: 'funcionario@villavicencio.gov.co',
    role: 'funcionario',
    dependencia: 'Secretaria Administrativa',
  },
  {
    id: 'usr-002',
    name: 'Secretario Demo',
    email: 'secretario@villavicencio.gov.co',
    role: 'secretario',
    dependencia: 'Secretaria General',
  },
  {
    id: 'usr-003',
    name: 'Administrador Demo',
    email: 'admin@villavicencio.gov.co',
    role: 'administrador',
    dependencia: 'Direccion de Talento Humano',
  },
];
