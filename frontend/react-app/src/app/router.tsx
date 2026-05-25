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
        <RoleRoute allowedRoles={['funcionario', 'secretario']}>
          <AppLayout variant="app" />
        </RoleRoute>
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <Navigate replace to="/app/dashboard" /> },
      { path: 'dashboard', element: <FuncionarioDashboardPage /> },
      { path: 'solicitudes', element: <SolicitudesPage /> },
      { path: 'solicitudes/nueva', element: <RoleRoute allowedRoles={['funcionario']}><SolicitudCertificadoPage /></RoleRoute> },
      { path: 'solicitudes/:id', element: <SolicitudDetailPage /> },
      { path: 'pagos', element: <RoleRoute allowedRoles={['secretario']}><PagosPage /></RoleRoute> },
      { path: 'certificados', element: <CertificadosPage /> },
      { path: 'certificados/:id', element: <CertificadoDetailPage /> },
    ],
  },
  {
    path: '/admin',
    element: (
      <ProtectedRoute>
        <RoleRoute allowedRoles={['admin']}>
          <AppLayout variant="admin" />
        </RoleRoute>
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <Navigate replace to="/admin/dashboard" /> },
      { path: 'dashboard', element: <AdminDashboardPage /> },
      { path: 'funcionarios', element: <FuncionariosPage /> },
      { path: 'funcionarios/nuevo', element: <FuncionarioFormPage /> },
      { path: 'funcionarios/:id', element: <FuncionarioDetailPage /> },
      { path: 'funcionarios/:id/editar', element: <FuncionarioFormPage /> },
      { path: 'cargos', element: <CargosPage /> },
      { path: 'rangos-salariales', element: <RangosSalarialesPage /> },
      { path: 'solicitudes', element: <SolicitudesPage /> },
      { path: 'solicitudes/:id', element: <SolicitudDetailPage /> },
      { path: 'pagos', element: <PagosPage /> },
      { path: 'certificados', element: <CertificadosPage /> },
      { path: 'certificados/:id', element: <CertificadoDetailPage /> },
      { path: 'auditoria', element: <AuditoriaPage /> },
      { path: 'reportes', element: <ReportesPage /> },
    ],
  },
  {
    path: '*',
    element: <Navigate replace to="/login" />,
  },
]);
