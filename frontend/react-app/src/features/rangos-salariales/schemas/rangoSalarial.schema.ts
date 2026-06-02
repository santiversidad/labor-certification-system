import { z } from 'zod';

export const rangoSalarialSchema = z.object({
  codigo: z.string().min(1, 'Ingrese codigo.').max(10, 'Maximo 10 caracteres.'),
  grado: z.string().min(1, 'Ingrese grado.'),
  vigencia_anio: z.coerce.number().int().min(2000, 'Vigencia invalida.').max(2100, 'Vigencia invalida.'),
  salario_basico: z.coerce.number().positive('Ingrese salario basico.'),
  moneda: z.string().length(3, 'Use el codigo de moneda de 3 letras.').default('COP'),
  observaciones: z.string().optional(),
  estado: z.boolean().default(true),
});

export type RangoSalarialFormValues = z.input<typeof rangoSalarialSchema>;
