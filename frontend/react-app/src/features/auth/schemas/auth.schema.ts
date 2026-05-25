import { z } from 'zod';

export const loginSchema = z.object({
  cedula: z.string().min(1, 'Ingrese la cedula.').max(20, 'Maximo 20 caracteres.'),
  password: z.string().min(1, 'Ingrese una contrasena.'),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
