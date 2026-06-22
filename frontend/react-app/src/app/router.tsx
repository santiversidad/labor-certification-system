import { createBrowserRouter, Navigate } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { AuthLayout } from '../components/layout/AuthLayout';
import { PublicLayout } from '../components/layout/PublicLayout';
import { ProtectedRoute } from '../components/guards/ProtectedRoute';
import { RoleRoute } from '../components/guards/RoleRoute';
import { LoginPage } from '../features/auth/pages/LoginPage';
import { AuditoriaPage } from '../features/auditoria/pages/AuditoriaPage';
import { CargosPage } from '../features/cargos/pages/CargosPage';
import { CertificadoDetailPage } from '../features/certificados/pages/CertificadoDetailPage';
import { CertificadosPage } from '../features/certificados/pages/CertificadosPage';
import { AdminDashboardPage } from '../features/dashboard/pages/AdminDashboardPage';
import { FuncionarioDashboardPage } from '../features/dashboard/pages/FuncionarioDashboardPage';
import { FuncionarioDetailPage } from '../features/funcionarios/pages/FuncionarioDetailPage';
import { FuncionarioFormPage } from '../features/funcionarios/pages/FuncionarioFormPage';
import { FuncionariosPage } from '../features/funcionarios/pages/FuncionariosPage';
import { PagosPage } from '../features/pagos/pages/PagosPage';
import { RangosSalarialesPage } from '../features/rangos-salariales/pages/RangosSalarialesPage';
import { ReportesPage } from '../features/reportes/pages/ReportesPage';
import { SolicitudConfirmacionPage } from '../features/solicitudes/pages/SolicitudConfirmacionPage';
import { SolicitudCertificadoPage } from '../features/solicitudes/pages/SolicitudCertificadoPage';
import { SolicitudDetailPage } from '../features/solicitudes/pages/SolicitudDetailPage';
import { SolicitudesPage } from '../features/solicitudes/pages/SolicitudesPage';
import { ValidarCertificadoPage } from '../features/validacion-publica/pages/ValidarCertificadoPage';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Navigate replace to="/login" />,
  },
  {
    element: <AuthLayout />,
    children: [
      { path: '/login', element: <LoginPage /> },
    ],
  },
  {
    element: <PublicLayout />,
    children: [
      { path: '/validar-certificado/:token', element: <ValidarCertificadoPage /> },
    ],
  },
  {
    path: '/app',
    element: (
      <ProtectedRoute>
        <RoleRoute allowedRoles={['funcionario']}>
          <AppLayout variant="app" />
        </RoleRoute>
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <Navigate replace to="/app/inicio" /> },
      { path: 'inicio', element: <FuncionarioDashboardPage /> },
      { path: 'solicitudes', element: <SolicitudesPage /> },
      { path: 'solicitudes/nueva', element: <SolicitudCertificadoPage /> },
      { path: 'solicitudes/confirmacion', element: <SolicitudConfirmacionPage /> },
      { path: 'solicitudes/:id', element: <SolicitudDetailPage /> },
    ],
  },
  {
    path: '/admin',
    element: (
      <ProtectedRoute>
        <RoleRoute allowedRoles={['admin', 'secretario']}>
          <AppLayout variant="admin" />
        </RoleRoute>
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <Navigate replace to="/admin/dashboard" /> },
      { path: 'dashboard', element: <AdminDashboardPage /> },
      // Rutas de solo-admin
      { path: 'funcionarios', element: <RoleRoute allowedRoles={['admin']}><FuncionariosPage /></RoleRoute> },
      { path: 'funcionarios/nuevo', element: <RoleRoute allowedRoles={['admin']}><FuncionarioFormPage /></RoleRoute> },
      { path: 'funcionarios/:id', element: <RoleRoute allowedRoles={['admin']}><FuncionarioDetailPage /></RoleRoute> },
      { path: 'funcionarios/:id/editar', element: <RoleRoute allowedRoles={['admin']}><FuncionarioFormPage /></RoleRoute> },
      { path: 'cargos', element: <RoleRoute allowedRoles={['admin']}><CargosPage /></RoleRoute> },
      { path: 'rangos-salariales', element: <RoleRoute allowedRoles={['admin']}><RangosSalarialesPage /></RoleRoute> },
      { path: 'auditoria', element: <RoleRoute allowedRoles={['admin']}><AuditoriaPage /></RoleRoute> },
      { path: 'reportes', element: <RoleRoute allowedRoles={['admin']}><ReportesPage /></RoleRoute> },
      // Rutas compartidas admin + secretario
      { path: 'solicitudes', element: <SolicitudesPage /> },
      { path: 'solicitudes/:id', element: <SolicitudDetailPage /> },
      { path: 'pagos', element: <PagosPage /> },
      { path: 'certificados', element: <CertificadosPage /> },
      { path: 'certificados/:id', element: <CertificadoDetailPage /> },
    ],
  },
  {
    path: '*',
    element: <Navigate replace to="/login" />,
  },
]);
