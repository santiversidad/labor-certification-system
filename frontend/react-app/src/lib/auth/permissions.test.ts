import { expect, it } from 'vitest';
import type { User } from '../../types/common.types';
import { getHomePath, hasAnyRole } from './permissions';

const base: User = { id: 1, name: 'María Test', username: 'maria.test', documento: null,
  tipo_cuenta: 'backoffice', estado: true, must_change_password: false, roles: ['admin'], permisos: [] };
it('cada tipo de cuenta conserva su contexto y rechaza roles mixtos', () => {
  expect(hasAnyRole(base, ['funcionario'])).toBe(false);
  expect(getHomePath(base)).toBe('/admin/dashboard');
  expect(getHomePath({ ...base, roles: ['secretario'] })).toBe('/admin/dashboard');
  expect(getHomePath({ ...base, tipo_cuenta: 'funcionario', roles: ['funcionario'] })).toBe('/app/inicio');
  expect(hasAnyRole({ ...base, roles: ['admin', 'funcionario'] }, ['admin'])).toBe(false);
  expect(hasAnyRole({ ...base, tipo_cuenta: 'funcionario' }, ['admin'])).toBe(false);
  expect(getHomePath({ ...base, tipo_cuenta: 'legacy' })).toBe('/login');
});
