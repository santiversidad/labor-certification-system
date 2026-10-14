import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { CertificateType, PreviewScenario, Template, TemplateAsset, TemplateContent, TemplateDetail, TemplateVersion, Variable } from '../types/template.types';

const root = '/admin/plantillas-certificados';
export const templatesService = {
  async list() { return (await apiClient.get<ApiResponse<Template[]>>(root)).data.data; },
  async catalog(tipo: CertificateType) { return (await apiClient.get<ApiResponse<{ variables: Variable[]; fonts: string[] }>>(root + '/catalogo', { params: { tipo } })).data.data; },
  async create(id: number) { return (await apiClient.post<ApiResponse<TemplateDetail>>(`${root}/${id}/versiones`, {})).data.data; },
  async detail(id: number) { return (await apiClient.get<ApiResponse<TemplateDetail>>(`${root}/versiones/${id}`)).data.data; },
  async history(id: number, page = 1) { return (await apiClient.get<ApiResponse<TemplateVersion[]>>(`${root}/${id}/versiones`, { params: { page } })).data; },
  async save(id: number, revision: number, contenido: TemplateContent) { return (await apiClient.put<ApiResponse<TemplateDetail>>(`${root}/versiones/${id}`, { revision, contenido })).data.data; },
  async publish(id: number, revision: number) { return (await apiClient.post<ApiResponse<TemplateDetail>>(`${root}/versiones/${id}/publicar`, { revision, confirmado: true })).data.data; },
  async preview(id: number, contenido: TemplateContent, escenario: PreviewScenario) { return (await apiClient.post<Blob>(`${root}/versiones/${id}/preview`, { contenido, escenario }, { responseType: 'blob' })).data; },
  async upload(id: number, posicion: 'left' | 'right', archivo: File, procedencia: string) {
    const data = new FormData(); data.append('archivo', archivo); data.append('posicion', posicion); data.append('procedencia', procedencia);
    return (await apiClient.post<ApiResponse<TemplateAsset>>(`${root}/versiones/${id}/assets`, data)).data.data;
  },
};
