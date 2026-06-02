export const endpoints = {
  auth: {
    login: '/auth/login',
    me: '/auth/me',
  },
  dashboard: '/dashboard',
  funcionarios: '/funcionarios',
  cargos: '/cargos',
  rangosSalariales: '/rangos-salariales',
  solicitudes: '/solicitudes',
  pagos: '/pagos',
  certificados: '/certificados',
  validacionPublica: (token: string) => `/certificados/validar/${token}`,
  auditoria: '/auditoria',
  reportes: '/reportes',
};
