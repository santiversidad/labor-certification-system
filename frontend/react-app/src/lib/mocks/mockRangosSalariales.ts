import type { RangoSalarial } from '../../features/rangos-salariales/types/rangoSalarial.types';

export const mockRangosSalariales: RangoSalarial[] = [
  {
    id: 'ran-001',
    cargoId: 'car-001',
    grado: '12',
    salarioBase: 4860000,
    vigenciaDesde: '2026-01-01',
    activo: true,
  },
  {
    id: 'ran-002',
    cargoId: 'car-002',
    grado: '08',
    salarioBase: 3250000,
    vigenciaDesde: '2026-01-01',
    activo: true,
  },
];
