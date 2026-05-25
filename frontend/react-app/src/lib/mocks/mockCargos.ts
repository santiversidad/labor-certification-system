import type { Cargo } from '../../features/cargos/types/cargo.types';

export const mockCargos: Cargo[] = [
  {
    id: 'car-001',
    nombre: 'Profesional Universitario',
    grado: '12',
    dependencia: 'Talento Humano',
    activo: true,
  },
  {
    id: 'car-002',
    nombre: 'Tecnico Administrativo',
    grado: '08',
    dependencia: 'Hacienda',
    activo: true,
  },
];
