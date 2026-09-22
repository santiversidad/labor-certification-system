import type { LucideIcon } from 'lucide-react';
import { BadgeCheck, BookOpenCheck, BriefcaseBusiness, FileBarChart, Gauge, Landmark, ScrollText, Settings, Users } from 'lucide-react';
import type { Role } from '../types/roles.types';

export type NavigationItem = {
  label: string;
  path: string;
  icon: LucideIcon;
  roles: Role[];
};

// Rutas del funcionario (portal personal)
export const appNavigation: NavigationItem[] = [
  { label: 'Certificaciones laborales', path: '/app/inicio', icon: BadgeCheck, roles: ['funcionario'] },
];

// El rol secretario conserva únicamente consulta operativa; no expone aprobaciones manuales.
export const adminNavigation: NavigationItem[] = [
  { label: 'Dashboard', path: '/admin/dashboard', icon: Gauge, roles: ['admin', 'secretario'] },
  { label: 'Funcionarios', path: '/admin/funcionarios', icon: Users, roles: ['admin'] },
  { label: 'Cargos', path: '/admin/cargos', icon: BriefcaseBusiness, roles: ['admin'] },
  { label: 'Rangos salariales', path: '/admin/rangos-salariales', icon: Landmark, roles: ['admin'] },
  { label: 'Manual de Funciones', path: '/admin/manual-funciones', icon: BookOpenCheck, roles: ['admin'] },
  { label: 'Certificaciones', path: '/admin/certificaciones', icon: BadgeCheck, roles: ['admin', 'secretario'] },
  { label: 'Configuración', path: '/admin/configuracion', icon: Settings, roles: ['admin'] },
  { label: 'Auditoría', path: '/admin/auditoria', icon: ScrollText, roles: ['admin'] },
  { label: 'Reportes', path: '/admin/reportes', icon: FileBarChart, roles: ['admin'] },
];
