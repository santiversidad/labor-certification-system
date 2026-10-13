import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';

export type Dependencia = { id: string; nombre: string; normalizacion?: string; fichas_count?: number };
export type CargoDependencia = { id: number; denominacion: string; codigo: string; grado: string; perfiles_count: number;
  manual_cargo_version_id?: number; source_id?: string | null; pagina_inicio?: number | null; pagina_verificada?: boolean;
  area_funcional?: string; dependencia?: string; version?: string; version_id?: number; transversal?: boolean; seleccionable?: boolean; funciones_count?: number };
export type PerfilAsociado = { source_id: string | null; funciones_count: number } & Partial<CargoDependencia>;
export type FuncionesPerfil = PerfilAsociado & { funciones: { orden: number; descripcion: string; numero_fuente: number | null; grupo: string | null }[] };
export const dependenciasService = {
  async list(fecha: string, q: string): Promise<Dependencia[]> {
    return (await apiClient.get<ApiResponse<Dependencia[]>>('/manual-funciones/dependencias', { params: { fecha, q } })).data.data;
  },
  async cargos(unidad: string, fecha: string, q: string): Promise<CargoDependencia[]> {
    const perfiles: CargoDependencia[] = [];
    let page = 1;
    let lastPage: number;
    do {
      const response = (await apiClient.get<ApiResponse<CargoDependencia[]>>('/manual-funciones/dependencias/cargos', { params: { unidad_organizacional_id: unidad, fecha, q, per_page: 100, perfiles: 1, page } })).data;
      perfiles.push(...response.data);
      lastPage = response.meta?.last_page ?? 1;
      page++;
    } while (page <= lastPage);
    return perfiles;
  },
  async resolver(unidad: string, cargo: string, fecha: string, ficha?: string): Promise<PerfilAsociado> {
    return (await apiClient.get<ApiResponse<PerfilAsociado>>('/manual-funciones/dependencias/resolver', { params: { unidad_organizacional_id: unidad, cargo_id: cargo, fecha, ...(ficha ? { manual_cargo_version_id: ficha } : {}) } })).data.data;
  },
  async funciones(unidad: string, cargo: string, fecha: string, ficha: string): Promise<FuncionesPerfil> {
    return (await apiClient.get<ApiResponse<FuncionesPerfil>>('/manual-funciones/dependencias/resolver', { params: { unidad_organizacional_id: unidad, cargo_id: cargo, fecha, manual_cargo_version_id: ficha, funciones: 1 } })).data.data;
  },
  async funcionesRegistradas(funcionario: string, ficha: string): Promise<FuncionesPerfil> {
    return (await apiClient.get<ApiResponse<FuncionesPerfil>>(`/manual-funciones/dependencias/funciones-registradas/${ficha}`, { params: { funcionario_id: funcionario } })).data.data;
  },
};
