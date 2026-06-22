import type { LucideIcon } from 'lucide-react';
import { BadgeCheck, BriefcaseBusiness, ClipboardList, CreditCard, FileCheck, Gauge, Landmark, ScrollText, Users } from 'lucide-react';
import type { Role } from '../types/roles.types';

export type NavigationItem = {
  label: string;
  path: string;
  icon: LucideIcon;
  roles: Role[];
};

// Rutas del funcionario (portal personal)
export const appNavigation: NavigationItem[] = [
  { label: 'Mis solicitudes',  path: '/app/solicitudes',         icon: ClipboardList, roles: ['funcionario'] },
  { label: 'Nueva solicitud',  path: '/app/solicitudes/nueva',   icon: FileCheck,     roles: ['funcionario'] },
];

// Rutas del panel de gestión (secretario + admin)
export const adminNavigation: NavigationItem[] = [
  { label: 'Dashboard',        path: '/admin/dashboard',         icon: Gauge,         roles: ['admin', 'secretario'] },
  { label: 'Solicitudes',      path: '/admin/solicitudes',       icon: ClipboardList, roles: ['admin', 'secretario'] },
  { label: 'Pagos',            path: '/admin/pagos',             icon: CreditCard,    roles: ['admin', 'secretario'] },
  { label: 'Certificados',     path: '/admin/certificados',      icon: BadgeCheck,    roles: ['admin', 'secretario'] },
  { label: 'Funcionarios',     path: '/admin/funcionarios',      icon: Users,         roles: ['admin'] },
  { label: 'Cargos y grados',  path: '/admin/cargos',            icon: BriefcaseBusiness, roles: ['admin'] },
  { label: 'Rangos salariales',path: '/admin/rangos-salariales', icon: Landmark,      roles: ['admin'] },
  { label: 'Auditoria',        path: '/admin/auditoria',         icon: ScrollText,    roles: ['admin'] },
  { label: 'Reportes',         path: '/admin/reportes',          icon: FileCheck,     roles: ['admin'] },
];
