import type { User } from '../../types/common.types';

export const mockUsers: User[] = [
  {
    id: 'usr-001',
    name: 'Funcionario Demo',
    documento: '000000003',
    telefono: null,
    estado: true,
    roles: ['funcionario'],
    permisos: ['solicitudes.ver', 'solicitudes.crear', 'pagos.ver', 'certificados.ver', 'certificados.descargar'],
    funcionario: {
      id: 'fun-001',
      correo_institucional: 'funcionario@villavicencio.gov.co',
      dependencia: 'Secretaria Administrativa',
    },
  },
  {
    id: 'usr-002',
    name: 'Secretario Demo',
    documento: '000000002',
    telefono: null,
    estado: true,
    roles: ['secretario'],
    permisos: ['funcionarios.ver', 'cargos.ver', 'rangos_salariales.ver', 'solicitudes.ver', 'pagos.ver', 'certificados.ver'],
    funcionario: {
      id: 'fun-002',
      correo_institucional: 'secretario@villavicencio.gov.co',
      dependencia: 'Secretaria General',
    },
  },
  {
    id: 'usr-003',
    name: 'Administrador Demo',
    documento: '000000001',
    telefono: null,
    estado: true,
    roles: ['admin'],
    permisos: ['usuarios.ver', 'funcionarios.ver', 'cargos.ver', 'rangos_salariales.ver', 'auditoria.ver'],
    funcionario: {
      id: 'fun-003',
      correo_institucional: 'admin@villavicencio.gov.co',
      dependencia: 'Direccion de Talento Humano',
    },
  },
];
