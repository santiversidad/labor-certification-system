import { z } from 'zod';

export const solicitudSchema = z.object({
  tipo: z.enum(['laboral', 'salarial', 'funciones']),
  incluyeSalario: z.boolean(),
  observaciones: z.string().max(500, 'Maximo 500 caracteres.').optional(),
});

export type SolicitudFormValues = z.infer<typeof solicitudSchema>;
