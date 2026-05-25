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
  { label: 'Dashboard', path: '/admin/dashboard', icon: Gauge, roles: ['administrador'] },
  { label: 'Funcionarios', path: '/admin/funcionarios', icon: Users, roles: ['administrador'] },
  { label: 'Cargos y grados', path: '/admin/cargos', icon: BriefcaseBusiness, roles: ['administrador'] },
  { label: 'Rangos salariales', path: '/admin/rangos-salariales', icon: Landmark, roles: ['administrador'] },
  { label: 'Solicitudes', path: '/admin/solicitudes', icon: ClipboardList, roles: ['administrador'] },
  { label: 'Pagos', path: '/admin/pagos', icon: CreditCard, roles: ['administrador'] },
  { label: 'Certificados', path: '/admin/certificados', icon: BadgeCheck, roles: ['administrador'] },
  { label: 'Auditoria', path: '/admin/auditoria', icon: ScrollText, roles: ['administrador'] },
  { label: 'Reportes', path: '/admin/reportes', icon: FileCheck, roles: ['administrador'] },
];
