import type { Funcionario } from '../../features/funcionarios/types/funcionario.types';

export const mockFuncionarios: Funcionario[] = [
  {
    id: 'fun-001',
    nombres: 'Laura Marcela',
    apellidos: 'Gomez Rojas',
    documento: '1020304050',
    email: 'laura.gomez@villavicencio.gov.co',
    dependencia: 'Talento Humano',
    cargoId: 'car-001',
    estado: 'activo',
    fechaIngreso: '2021-02-15',
  },
  {
    id: 'fun-002',
    nombres: 'Carlos Andres',
    apellidos: 'Martinez Silva',
    documento: '80706050',
    email: 'carlos.martinez@villavicencio.gov.co',
    dependencia: 'Hacienda',
    cargoId: 'car-002',
    estado: 'activo',
    fechaIngreso: '2019-08-01',
  },
];
