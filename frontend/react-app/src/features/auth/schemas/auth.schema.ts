import { z } from 'zod';

export const loginSchema = z.object({
  email: z.string().email('Ingrese un correo institucional valido.'),
  password: z.string().min(1, 'Ingrese una contrasena.'),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
