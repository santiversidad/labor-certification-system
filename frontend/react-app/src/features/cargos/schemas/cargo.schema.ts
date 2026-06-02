import { z } from 'zod';

export const cargoSchema = z.object({
  codigo: z.string().min(1, 'Ingrese codigo.').max(10, 'Maximo 10 caracteres.'),
  grado: z.string().min(1, 'Ingrese grado.'),
  denominacion: z.string().min(2, 'Ingrese denominacion del cargo.').max(150, 'Maximo 150 caracteres.'),
  nivel: z.string().max(60, 'Maximo 60 caracteres.').optional(),
  dependencia: z.string().max(150, 'Maximo 150 caracteres.').optional(),
  estado: z.boolean().default(true),
});

export type CargoFormValues = z.input<typeof cargoSchema>;
