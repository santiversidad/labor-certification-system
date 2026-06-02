import type { Cargo } from '../../features/cargos/types/cargo.types';

export const mockCargos: Cargo[] = [
  {
    id: 'car-001',
    codigo: '219',
    grado: '12',
    denominacion: 'Profesional Universitario',
    nivel: 'Profesional',
    dependencia: 'Talento Humano',
    estado: true,
  },
  {
    id: 'car-002',
    codigo: '367',
    grado: '08',
    denominacion: 'Tecnico Administrativo',
    nivel: 'Tecnico',
    dependencia: 'Hacienda',
    estado: true,
  },
];
