export type AuditLog = {
  id: number;
  user_id?: number | null;
  accion: string;
  modelo: string;
  modelo_id?: number | null;
  descripcion?: string | null;
  metadata?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  created_at: string;

  user?: { id: number; name: string; documento: string };
};
