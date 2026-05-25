import { z } from 'zod';

export const rangoSalarialSchema = z.object({
  cargoId: z.string().min(1, 'Seleccione cargo.'),
  grado: z.string().min(1, 'Ingrese grado.'),
  salarioBase: z.coerce.number().positive('Ingrese salario base.'),
  vigenciaDesde: z.string().min(1, 'Ingrese fecha de inicio.'),
});

export type RangoSalarialFormValues = z.input<typeof rangoSalarialSchema>;
