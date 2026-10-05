import { z } from 'zod';

// Preventive input bounds; these do not represent a statutory increase limit.
export const percentageSchema = z.string().regex(/^\d{1,3}([.,]\d{1,2})?$/).refine((value) => Number(value.replace(',', '.')) <= 100);
export const salarySchema = z.string().regex(/^[1-9]\d{0,11}$/);
export const countSchema = z.string().regex(/^\d{1,7}$/).refine((value) => Number(value) <= 1000000);
export const cop = (value: string | null) => value === null ? 'Pendiente' : new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(value));
export function salaryError(error: unknown): string {
  const value = error as { message?: string; errors?: Record<string, string[]>; response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
  const data = value.response?.data ?? value;
  return Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'No pudimos consultar la escala salarial. Inténtelo nuevamente.';
}
