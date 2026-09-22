/* eslint-disable react-refresh/only-export-components -- route-level lazy components are intentionally colocated */
import { lazy, Suspense, type ComponentType } from 'react';
import { createBrowserRouter, Navigate } from 'react-router-dom';
import { LoadingState } from '../components/feedback/LoadingState';
import { ProtectedRoute } from '../components/guards/ProtectedRoute';
import { RoleRoute } from '../components/guards/RoleRoute';
import { AppLayout } from '../components/layout/AppLayout';
import { AuthLayout } from '../components/layout/AuthLayout';
import { PublicLayout } from '../components/layout/PublicLayout';

const LoginPage = lazy(() => import('../features/auth/pages/LoginPage').then((module) => ({ default: module.LoginPage })));
const ChangePasswordPage = lazy(() => import('../features/auth/pages/ChangePasswordPage').then((module) => ({ default: module.ChangePasswordPage })));
const ValidarCertificadoPage = lazy(() => import('../features/validacion-publica/pages/ValidarCertificadoPage').then((module) => ({ default: module.ValidarCertificadoPage })));
const FuncionarioDashboardPage = lazy(() => import('../features/dashboard/pages/FuncionarioDashboardPage').then((module) => ({ default: module.FuncionarioDashboardPage })));
const SolicitudCertificadoPage = lazy(() => import('../features/solicitudes/pages/SolicitudCertificadoPage').then((module) => ({ default: module.SolicitudCertificadoPage })));
const SolicitudConfirmacionPage = lazy(() => import('../features/solicitudes/pages/SolicitudConfirmacionPage').then((module) => ({ default: module.SolicitudConfirmacionPage })));
const AdminDashboardPage = lazy(() => import('../features/dashboard/pages/AdminDashboardPage').then((module) => ({ default: module.AdminDashboardPage })));
const FuncionariosPage = lazy(() => import('../features/funcionarios/pages/FuncionariosPage').then((module) => ({ default: module.FuncionariosPage })));
const FuncionarioFormPage = lazy(() => import('../features/funcionarios/pages/FuncionarioFormPage').then((module) => ({ default: module.FuncionarioFormPage })));
const FuncionarioDetailPage = lazy(() => import('../features/funcionarios/pages/FuncionarioDetailPage').then((module) => ({ default: module.FuncionarioDetailPage })));
const CargosPage = lazy(() => import('../features/cargos/pages/CargosPage').then((module) => ({ default: module.CargosPage })));
const RangosSalarialesPage = lazy(() => import('../features/rangos-salariales/pages/RangosSalarialesPage').then((module) => ({ default: module.RangosSalarialesPage })));
const ManualesPage = lazy(() => import('../features/manuales/pages/ManualesPage').then((module) => ({ default: module.ManualesPage })));
const CertificadosPage = lazy(() => import('../features/certificados/pages/CertificadosPage').then((module) => ({ default: module.CertificadosPage })));
const CertificadoDetailPage = lazy(() => import('../features/certificados/pages/CertificadoDetailPage').then((module) => ({ default: module.CertificadoDetailPage })));
const ConfiguracionCertificacionesPage = lazy(() => import('../features/configuracion/pages/ConfiguracionCertificacionesPage').then((module) => ({ default: module.ConfiguracionCertificacionesPage })));
const AuditoriaPage = lazy(() => import('../features/auditoria/pages/AuditoriaPage').then((module) => ({ default: module.AuditoriaPage })));
const ReportesPage = lazy(() => import('../features/reportes/pages/ReportesPage').then((module) => ({ default: module.ReportesPage })));

function screen(Component: ComponentType) {
  return <Suspense fallback={<LoadingState label="Cargando pantalla..." />}><Component /></Suspense>;
}

export const router = createBrowserRouter([
  { path: '/', element: <Navigate replace to="/login" /> },
  {
    element: <AuthLayout />,
    children: [
      { path: '/login', element: screen(LoginPage) },
      { path: '/cambiar-contrasena', element: <ProtectedRoute>{screen(ChangePasswordPage)}</ProtectedRoute> },
    ],
  },
  { element: <PublicLayout />, children: [{ path: '/validar-certificado/:token?', element: screen(ValidarCertificadoPage) }] },
  {
    path: '/app',
    element: <ProtectedRoute><RoleRoute allowedRoles={['funcionario']}><AppLayout variant="app" /></RoleRoute></ProtectedRoute>,
    children: [
      { index: true, element: <Navigate replace to="/app/inicio" /> },
      { path: 'inicio', element: screen(FuncionarioDashboardPage) },
      { path: 'dashboard', element: <Navigate replace to="/app/inicio" /> },
      { path: 'solicitudes/nueva', element: screen(SolicitudCertificadoPage) },
      { path: 'solicitudes/confirmacion', element: screen(SolicitudConfirmacionPage) },
    ],
  },
  {
    path: '/admin',
    element: <ProtectedRoute><RoleRoute allowedRoles={['admin', 'secretario']}><AppLayout variant="admin" /></RoleRoute></ProtectedRoute>,
    children: [
      { index: true, element: <Navigate replace to="/admin/dashboard" /> },
      { path: 'dashboard', element: screen(AdminDashboardPage) },
      { path: 'funcionarios', element: <RoleRoute allowedRoles={['admin']}>{screen(FuncionariosPage)}</RoleRoute> },
      { path: 'funcionarios/nuevo', element: <RoleRoute allowedRoles={['admin']}>{screen(FuncionarioFormPage)}</RoleRoute> },
      { path: 'funcionarios/:id', element: <RoleRoute allowedRoles={['admin']}>{screen(FuncionarioDetailPage)}</RoleRoute> },
      { path: 'funcionarios/:id/editar', element: <RoleRoute allowedRoles={['admin']}>{screen(FuncionarioFormPage)}</RoleRoute> },
      { path: 'cargos', element: <RoleRoute allowedRoles={['admin']}>{screen(CargosPage)}</RoleRoute> },
      { path: 'rangos-salariales', element: <RoleRoute allowedRoles={['admin']}>{screen(RangosSalarialesPage)}</RoleRoute> },
      { path: 'manual-funciones', element: <RoleRoute allowedRoles={['admin']}>{screen(ManualesPage)}</RoleRoute> },
      { path: 'certificaciones', element: screen(CertificadosPage) },
      { path: 'certificaciones/:id', element: screen(CertificadoDetailPage) },
      { path: 'configuracion', element: <RoleRoute allowedRoles={['admin']}>{screen(ConfiguracionCertificacionesPage)}</RoleRoute> },
      { path: 'configuracion-certificaciones', element: <Navigate replace to="/admin/configuracion" /> },
      { path: 'auditoria', element: <RoleRoute allowedRoles={['admin']}>{screen(AuditoriaPage)}</RoleRoute> },
      { path: 'reportes', element: <RoleRoute allowedRoles={['admin']}>{screen(ReportesPage)}</RoleRoute> },
    ],
  },
  { path: '*', element: <Navigate replace to="/login" /> },
]);
