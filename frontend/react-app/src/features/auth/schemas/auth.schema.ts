import { z } from 'zod';

export const loginSchema = z.object({
  username: z.string().trim().min(1, 'Ingrese el usuario.').max(64, 'Máximo 64 caracteres.'),
  password: z.string().min(1, 'Ingrese una contrasena.'),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
