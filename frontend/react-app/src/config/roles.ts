import type { Role, RoleLabelMap } from '../types/roles.types';

export const roles: Role[] = ['funcionario', 'secretario', 'administrador'];

export const roleLabels: RoleLabelMap = {
  funcionario: 'Funcionario',
  secretario: 'Secretario',
  administrador: 'Administrador',
};
