import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';

export type Dependencia = { id: string; nombre: string; normalizacion?: string; fichas_count?: number };
export type CargoDependencia = { id: number; denominacion: string; codigo: string; grado: string; perfiles_count: number };
export type PerfilAsociado = { source_id: string | null; funciones_count: number };
export const dependenciasService = {
  async list(fecha: string, q: string): Promise<Dependencia[]> {
    return (await apiClient.get<ApiResponse<Dependencia[]>>('/manual-funciones/dependencias', { params: { fecha, q } })).data.data;
  },
  async cargos(unidad: string, fecha: string, q: string): Promise<CargoDependencia[]> {
    return (await apiClient.get<ApiResponse<CargoDependencia[]>>('/manual-funciones/dependencias/cargos', { params: { unidad_organizacional_id: unidad, fecha, q, per_page: 100 } })).data.data;
  },
  async resolver(unidad: string, cargo: string, fecha: string): Promise<PerfilAsociado> {
    return (await apiClient.get<ApiResponse<PerfilAsociado>>('/manual-funciones/dependencias/resolver', { params: { unidad_organizacional_id: unidad, cargo_id: cargo, fecha } })).data.data;
  },
};
