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
  solicitudesActions: {
    aprobar: (id: string) => `/solicitudes/${id}/aprobar`,
    rechazar: (id: string) => `/solicitudes/${id}/rechazar`,
    pendientePago: (id: string) => `/solicitudes/${id}/pendiente-pago`,
    generarCertificado: (id: string) => `/solicitudes/${id}/generar-certificado`,
  },
  pagos: '/pagos',
  pagosActions: {
    aprobar: (id: string) => `/pagos/${id}/aprobar`,
    rechazar: (id: string) => `/pagos/${id}/rechazar`,
  },
  certificados: '/certificados',
  certificadosActions: {
    anular: (id: string) => `/certificados/${id}/anular`,
  },
  validacionPublica: (token: string) => `/certificados/validar/${token}`,
  auditoria: '/auditoria',
  reportes: '/reportes',
};
