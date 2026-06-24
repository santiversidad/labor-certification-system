import { z } from 'zod';

export const rangoSalarialSchema = z.object({
  codigo: z.string().min(1, 'Ingrese codigo.').max(10, 'Maximo 10 caracteres.'),
  grado: z.string().min(1, 'Ingrese grado.'),
  denominacion: z.string().min(1, 'Ingrese la denominacion.').max(200, 'Maximo 200 caracteres.'),
  vigencia_fecha: z.string().min(1, 'Seleccione una vigencia.'),
  salario_basico: z.coerce.number().positive('Ingrese salario basico.'),
  moneda: z.string().length(3, 'Use el codigo de moneda de 3 letras.').default('COP'),
  observaciones: z.string().optional(),
  estado: z.boolean().default(true),
}).transform((data) => ({
  ...data,
  vigencia_anio: new Date(data.vigencia_fecha + 'T00:00:00').getFullYear(),
}));

export type RangoSalarialFormValues = z.input<typeof rangoSalarialSchema>;
export type RangoSalarialFormOutput = z.output<typeof rangoSalarialSchema>;
