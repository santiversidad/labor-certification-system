export type Funcionario = {
  id: string;
  nombres: string;
  apellidos: string;
  documento: string;
  email: string;
  dependencia: string;
  cargoId: string;
  estado: 'activo' | 'retirado' | 'suspendido';
  fechaIngreso: string;
};
