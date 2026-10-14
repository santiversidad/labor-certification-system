import { z } from 'zod';

export const solicitudSchema = z.object({
  tipo_certificado: z.enum(['sencillo', 'funciones']),
  observaciones: z.string().max(500, 'Máximo 500 caracteres.').optional(),
});

export type SolicitudFormValues = z.infer<typeof solicitudSchema>;
