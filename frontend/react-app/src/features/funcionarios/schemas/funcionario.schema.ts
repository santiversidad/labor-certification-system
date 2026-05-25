import { z } from 'zod';

export const funcionarioSchema = z.object({
  nombres: z.string().min(2, 'Ingrese nombres.'),
  apellidos: z.string().min(2, 'Ingrese apellidos.'),
  documento: z.string().min(6, 'Ingrese documento.'),
  email: z.string().email('Ingrese correo valido.'),
  dependencia: z.string().min(2, 'Ingrese dependencia.'),
  cargoId: z.string().min(1, 'Seleccione cargo.'),
});

export type FuncionarioFormValues = z.infer<typeof funcionarioSchema>;
