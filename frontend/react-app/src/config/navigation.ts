import type { LucideIcon } from 'lucide-react';
import { BriefcaseBusiness, FileCheck, Gauge, Landmark, ScrollText, Settings2, Users } from 'lucide-react';
import type { Role } from '../types/roles.types';

export type NavigationItem = {
  label: string;
  path: string;
  icon: LucideIcon;
  roles: Role[];
};

// Rutas del funcionario (portal personal)
export const appNavigation: NavigationItem[] = [
  { label: 'Solicitar certificación', path: '/app/inicio', icon: FileCheck, roles: ['funcionario'] },
];

// Rutas del panel de gestión (secretario + admin)
export const adminNavigation: NavigationItem[] = [
  { label: 'Dashboard',        path: '/admin/dashboard',         icon: Gauge,         roles: ['admin', 'secretario'] },
  { label: 'Funcionarios',     path: '/admin/funcionarios',      icon: Users,         roles: ['admin'] },
  { label: 'Cargos y grados',  path: '/admin/cargos',            icon: BriefcaseBusiness, roles: ['admin'] },
  { label: 'Rangos salariales',path: '/admin/rangos-salariales', icon: Landmark,      roles: ['admin'] },
  { label: 'Auditoria',        path: '/admin/auditoria',         icon: ScrollText,    roles: ['admin'] },
  { label: 'Reportes',         path: '/admin/reportes',          icon: FileCheck,     roles: ['admin'] },
  { label: 'Configuración',    path: '/admin/configuracion-certificaciones', icon: Settings2, roles: ['admin'] },
];
