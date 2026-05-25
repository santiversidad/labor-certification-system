import { z } from 'zod';

export const cargoSchema = z.object({
  nombre: z.string().min(2, 'Ingrese nombre del cargo.'),
  grado: z.string().min(1, 'Ingrese grado.'),
  dependencia: z.string().min(2, 'Ingrese dependencia.'),
});

export type CargoFormValues = z.infer<typeof cargoSchema>;
