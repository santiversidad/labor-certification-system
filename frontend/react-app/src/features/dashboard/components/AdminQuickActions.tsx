import { BadgeCheck, BriefcaseBusiness, ClipboardList, CreditCard, Landmark, ScrollText, Users } from 'lucide-react';
import { Card } from '../../../components/ui/Card';
import { AdminModuleCard } from './AdminModuleCard';

export function AdminQuickActions() {
  const modules = [
    { label: 'Funcionarios', description: 'Gestionar hojas laborales.', path: '/admin/funcionarios', icon: Users },
    { label: 'Cargos y grados', description: 'Administrar catalogo de cargos.', path: '/admin/cargos', icon: BriefcaseBusiness },
    { label: 'Rangos salariales', description: 'Actualizar vigencias salariales.', path: '/admin/rangos-salariales', icon: Landmark },
    { label: 'Solicitudes', description: 'Revisar tramites radicados.', path: '/admin/solicitudes', icon: ClipboardList },
    { label: 'Pagos', description: 'Validar soportes cargados.', path: '/admin/pagos', icon: CreditCard },
    { label: 'Auditoria', description: 'Consultar actividad del sistema.', path: '/admin/auditoria', icon: ScrollText },
    { label: 'Certificados', description: 'Consultar documentos generados.', path: '/admin/certificados', icon: BadgeCheck },
  ];

  return (
    <Card title="Accesos rapidos" description="Modulos administrativos principales.">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {modules.map((module) => <AdminModuleCard key={module.path} {...module} />)}
      </div>
    </Card>
  );
}
