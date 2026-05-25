import type { Role, RoleLabelMap } from '../types/roles.types';

export const roles: Role[] = ['funcionario', 'secretario', 'admin'];

export const roleLabels: RoleLabelMap = {
  funcionario: 'Funcionario',
  secretario: 'Secretario',
  admin: 'Administrador',
};
