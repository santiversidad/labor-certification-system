import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, BadgeCheck, Banknote, BookOpenCheck, FileText, UserCheck, UserX } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Skeleton } from '../../../components/ui/Skeleton';
import { Badge } from '../../../components/ui/Badge';
import { PageHeader } from '../../../components/ui/PageHeader';
import { certificadosService } from '../../certificados/services/certificados.service';
import { configuracionService } from '../../configuracion/services/configuracion.service';
import { funcionariosService } from '../../funcionarios/services/funcionarios.service';
import { manualesService } from '../../manuales/services/manuales.service';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import { RecentRequestsTable } from '../components/RecentRequestsTable';
import { useAuth } from '../../auth/hooks/useAuth';

export function AdminDashboardPage() {
  const { user } = useAuth();
  const isAdmin = Boolean(user?.roles.includes('admin'));
  const funcionarios = useQuery({ queryKey: ['dashboard-funcionarios'], queryFn: () => funcionariosService.list({ per_page: 100 }), enabled: isAdmin });
  const certificados = useQuery({ queryKey: ['dashboard-certificados'], queryFn: certificadosService.list, enabled: isAdmin });
  const solicitudes = useQuery({ queryKey: ['dashboard-solicitudes'], queryFn: solicitudesService.list, enabled: isAdmin });
  const manuales = useQuery({ queryKey: ['dashboard-manuales'], queryFn: manualesService.estado, enabled: isAdmin });
  const configuracion = useQuery({ queryKey: ['dashboard-configuracion'], queryFn: configuracionService.get, enabled: isAdmin });

  if (!isAdmin) {
    return <div className="app-page"><PageHeader eyebrow="Consulta institucional" title="Administración" description="La expedición es automática y no requiere aprobación de secretaría." /><section className="surface-section p-6"><h2 className="font-bold text-text">Flujo automático</h2><p className="mt-2 text-sm leading-6 text-muted">Secretaría no aprueba solicitudes, no valida comprobantes y no genera documentos manualmente.</p><Link className="mt-5 inline-flex font-semibold text-primary hover:underline" to="/admin/certificaciones">Consultar certificaciones</Link></section></div>;
  }

  const hasError = funcionarios.isError || certificados.isError || solicitudes.isError || manuales.isError || configuracion.isError;
  if (hasError) return <ErrorState message="No fue posible cargar uno o más indicadores administrativos." />;
  const staff = funcionarios.data?.data ?? [];
  const certificates = certificados.data?.data ?? [];
  const requests = solicitudes.data?.data ?? [];
  const currentMonth = new Date().toISOString().slice(0, 7);
  const certificatesThisMonth = certificates.filter((item) => (item.fecha_generacion ?? item.created_at ?? '').startsWith(currentMonth));
  const currentManual = manuales.data?.data.versiones.find((item) => item.id === manuales.data?.data.manual_vigente);
  const loading = funcionarios.isLoading || certificados.isLoading || solicitudes.isLoading || manuales.isLoading || configuracion.isLoading;
  const stats = [
    { label: 'Funcionarios activos', value: staff.filter((item) => item.estado === 'activo').length, icon: UserCheck },
    { label: 'Funcionarios inactivos', value: staff.filter((item) => item.estado !== 'activo').length, icon: UserX },
    { label: 'Certificaciones del mes', value: certificatesThisMonth.length, icon: BadgeCheck },
    { label: 'Con salario', value: requests.filter((item) => item.requiere_salario).length, icon: Banknote },
    { label: 'Sin salario', value: requests.filter((item) => !item.requiere_salario).length, icon: FileText },
    { label: 'Errores de expedición', value: requests.filter((item) => item.estado === 'fallida').length, icon: AlertTriangle },
  ];

  return (
    <div className="app-page">
      <PageHeader eyebrow="Resumen operativo" title="Dashboard" description="Estado actual de funcionarios, expediciones, Manual y configuración de pago." />
      <section aria-label="Indicadores" className="grid overflow-hidden rounded-lg border border-border bg-surface shadow-soft sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        {stats.map((stat, index) => <div className={`p-5 ${index ? 'border-t border-border sm:border-l sm:border-t-0' : ''}`} key={stat.label}><div className="flex items-center justify-between gap-3"><p className="text-sm font-semibold text-muted">{stat.label}</p><stat.icon className="text-primary" size={19} /></div>{loading ? <Skeleton className="mt-4 h-9 w-16" /> : <p className="mt-3 text-3xl font-bold tracking-tight text-text">{stat.value}</p>}</div>)}
      </section>
      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
        <section className="surface-section p-5 sm:p-6"><div className="mb-4"><h2 className="font-bold text-text">Expediciones recientes</h2><p className="mt-1 text-sm text-muted">Últimas solicitudes informadas por el servicio institucional.</p></div>{loading ? <Skeleton className="h-56 w-full" /> : <RecentRequestsTable solicitudes={requests.slice(0, 5)} />}</section>
        <aside className="surface-section divide-y divide-border p-5 sm:p-6">
          <div className="pb-5"><div className="flex items-center justify-between gap-3"><h2 className="font-bold text-text">Manual vigente</h2><BookOpenCheck className="text-primary" size={19} /></div>{loading ? <Skeleton className="mt-4 h-12" /> : currentManual ? <><p className="mt-3 text-xl font-bold text-text">{currentManual.version}</p><p className="mt-1 text-sm text-muted">{currentManual.acto}</p></> : <p className="mt-3 text-sm text-muted">No hay versión vigente informada.</p>}</div>
          <div className="py-5"><p className="text-sm font-bold text-text">Pago de certificaciones</p>{loading ? <Skeleton className="mt-3 h-8 w-28" /> : <Badge className="mt-3" tone={configuracion.data?.data.requiere_pago_certificado ? 'gold' : 'green'}>{configuracion.data?.data.requiere_pago_certificado ? 'ACTIVADO' : 'DESACTIVADO'}</Badge>}</div>
          <nav className="pt-4 text-sm font-semibold" aria-label="Accesos frecuentes"><Link className="block py-2 text-primary hover:underline" to="/admin/funcionarios/nuevo">Crear funcionario</Link><Link className="block py-2 text-primary hover:underline" to="/admin/manual-funciones">Gestionar Manual</Link><Link className="block py-2 text-primary hover:underline" to="/admin/configuracion">Configurar pagos</Link></nav>
        </aside>
      </div>
    </div>
  );
}
