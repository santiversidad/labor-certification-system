import type { LucideIcon } from 'lucide-react';
import { BadgeCheck, BriefcaseBusiness, ClipboardList, CreditCard, FileCheck, Gauge, Landmark, ScrollText, Users } from 'lucide-react';
import type { Role } from '../types/roles.types';

export type NavigationItem = {
  label: string;
  path: string;
  icon: LucideIcon;
  roles: Role[];
};

export const appNavigation: NavigationItem[] = [
  { label: 'Mi panel', path: '/app/dashboard', icon: Gauge, roles: ['funcionario', 'secretario'] },
  { label: 'Mis solicitudes', path: '/app/solicitudes', icon: ClipboardList, roles: ['funcionario', 'secretario'] },
  { label: 'Nueva solicitud', path: '/app/solicitudes/nueva', icon: FileCheck, roles: ['funcionario'] },
  { label: 'Mis certificados', path: '/app/certificados', icon: BadgeCheck, roles: ['funcionario', 'secretario'] },
  { label: 'Pagos', path: '/app/pagos', icon: CreditCard, roles: ['secretario'] },
];

export const adminNavigation: NavigationItem[] = [
  { label: 'Dashboard', path: '/admin/dashboard', icon: Gauge, roles: ['admin'] },
  { label: 'Funcionarios', path: '/admin/funcionarios', icon: Users, roles: ['admin'] },
  { label: 'Cargos y grados', path: '/admin/cargos', icon: BriefcaseBusiness, roles: ['admin'] },
  { label: 'Rangos salariales', path: '/admin/rangos-salariales', icon: Landmark, roles: ['admin'] },
  { label: 'Solicitudes', path: '/admin/solicitudes', icon: ClipboardList, roles: ['admin'] },
  { label: 'Pagos', path: '/admin/pagos', icon: CreditCard, roles: ['admin'] },
  { label: 'Certificados', path: '/admin/certificados', icon: BadgeCheck, roles: ['admin'] },
  { label: 'Auditoria', path: '/admin/auditoria', icon: ScrollText, roles: ['admin'] },
  { label: 'Reportes', path: '/admin/reportes', icon: FileCheck, roles: ['admin'] },
];
