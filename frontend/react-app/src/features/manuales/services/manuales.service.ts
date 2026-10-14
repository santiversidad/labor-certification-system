import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';

export type ManualVersion = {
  id: number; manual_id: number; nombre: string; version: string; acto: string;
  estado: 'vigente' | 'publicado' | 'borrador' | 'inactivo'; estado_dominio: string;
  vigencia_desde: string | null; vigencia_hasta: string | null;
  fichas: number; funciones: number; fecha_importacion: string | null; published_at: string | null;
};

export type ManualEstado = { manual_vigente: number | null; versiones: ManualVersion[] };
export type ManualDiff = { conteos: Record<'SIN_CAMBIOS' | 'MODIFICADA' | 'NUEVA' | 'RETIRADA' | 'AMBIGUA', number> };

export const manualesService = {
  async estado(): Promise<ApiResponse<ManualEstado>> {
    return (await apiClient.get<ApiResponse<ManualEstado>>(endpoints.manuales.estado)).data;
  },
  async crearVersion(manualId: number, data: Record<string, string>): Promise<ApiResponse<unknown>> {
    return (await apiClient.post<ApiResponse<unknown>>(endpoints.manuales.crearVersion(manualId), data)).data;
  },
  async importar(versionId: number, file: File, dryRun: boolean): Promise<ApiResponse<Record<string, unknown>>> {
    const data = new FormData();
    data.append('archivo', file);
    data.append('dry_run', dryRun ? '1' : '0');
    return (await apiClient.post<ApiResponse<Record<string, unknown>>>(endpoints.manuales.importar(versionId), data)).data;
  },
  async diff(from: number, to: number): Promise<ApiResponse<ManualDiff>> {
    return (await apiClient.get<ApiResponse<ManualDiff>>(endpoints.manuales.diff(from, to))).data;
  },
  async planificar(from: number, to: number): Promise<ApiResponse<unknown>> {
    return (await apiClient.post<ApiResponse<unknown>>(endpoints.manuales.planificar(from, to))).data;
  },
  async publicar(versionId: number, vigenciaDesde: string): Promise<ApiResponse<unknown>> {
    return (await apiClient.post<ApiResponse<unknown>>(endpoints.manuales.publicar(versionId), { vigencia_desde: vigenciaDesde })).data;
  },
};
