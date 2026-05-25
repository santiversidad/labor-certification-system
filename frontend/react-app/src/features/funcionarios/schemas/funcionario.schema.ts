import { z } from 'zod';

export const funcionarioSchema = z.object({
  user_id: z.string().optional(),
  tipo_documento: z.enum(['CC', 'CE', 'PA', 'TI']).default('CC'),
  numero_documento: z.string().min(6, 'Ingrese documento.').max(20, 'Maximo 20 caracteres.'),
  nombres: z.string().min(2, 'Ingrese nombres.'),
  apellidos: z.string().min(2, 'Ingrese apellidos.'),
  correo_institucional: z.string().email('Ingrese correo valido.').optional().or(z.literal('')),
  telefono: z.string().max(20, 'Maximo 20 caracteres.').optional(),
  estado: z.enum(['activo', 'retirado', 'suspendido']).default('activo'),
  fecha_ingreso: z.string().optional(),
  fecha_retiro: z.string().optional(),
  dependencia: z.string().max(150, 'Maximo 150 caracteres.').optional(),
  cargo_id: z.string().optional(),
});

export type FuncionarioFormValues = z.input<typeof funcionarioSchema>;
