import { BriefcaseBusiness, Landmark, ScrollText, Settings2, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { PageHeader } from '../../../components/ui/PageHeader';
import { useAuth } from '../../auth/hooks/useAuth';

const acciones = [
  { title: 'Funcionarios', description: 'Altas, cargos, estado y restablecimiento de acceso.', path: '/admin/funcionarios', icon: Users },
  { title: 'Cargos y grados', description: 'Catálogo institucional de empleos.', path: '/admin/cargos', icon: BriefcaseBusiness },
  { title: 'Rangos salariales', description: 'Fuentes salariales estructuradas por vigencia.', path: '/admin/rangos-salariales', icon: Landmark },
  { title: 'Configuración', description: 'Requisito de pago para la expedición automática.', path: '/admin/configuracion-certificaciones', icon: Settings2 },
  { title: 'Auditoría', description: 'Eventos de acceso, expedición y administración.', path: '/admin/auditoria', icon: ScrollText },
];

export function AdminDashboardPage() {
  const { user } = useAuth();
  const visibles = user?.roles.includes('admin') ? acciones : [];
  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Talento Humano" title="Administración institucional" description="Configure las fuentes que permiten que el autoservicio opere sin intervención humana." />
      {visibles.length ? (
        <div className="divide-y divide-border rounded-xl border border-border bg-white px-6">
          {visibles.map(({ title, description, path, icon: Icon }) => (
            <Link className="group flex items-center gap-4 py-5" key={path} to={path}>
              <span className="rounded-lg bg-slate-50 p-3 text-govBlue transition group-hover:bg-blue-50"><Icon size={22} /></span>
              <span className="min-w-0 flex-1"><strong className="block text-text">{title}</strong><small className="mt-1 block text-muted">{description}</small></span>
              <span className="text-sm text-govBlue opacity-0 transition group-hover:opacity-100">Abrir →</span>
            </Link>
          ))}
        </div>
      ) : <p className="rounded-xl border border-border bg-white p-6 text-sm text-muted">El rol secretario no participa en la expedición automática. Sus módulos institucionales independientes permanecen disponibles según permisos.</p>}
    </div>
  );
}
