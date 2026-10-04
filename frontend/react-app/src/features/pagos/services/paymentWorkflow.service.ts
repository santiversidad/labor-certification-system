import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { SolicitudPago } from '../../solicitudes/types/solicitud.types';

export const paymentWorkflowService = {
  async list(estado: string, page = 1) {
    return (await apiClient.get<ApiResponse<SolicitudPago[]>>('/secretaria/pagos', { params: { estado, page } })).data;
  },
  async detail(id: string | number) {
    return (await apiClient.get<ApiResponse<SolicitudPago>>(`/secretaria/pagos/${id}`)).data;
  },
  async confirm(id: number) {
    return (await apiClient.post<ApiResponse<SolicitudPago>>(`/secretaria/pagos/${id}/confirmar`)).data;
  },
  async reject(id: number, motivo: string) {
    return (await apiClient.post<ApiResponse<SolicitudPago>>(`/secretaria/pagos/${id}/rechazar`, { motivo })).data;
  },
  async upload(id: number, archivo: File, referencia: string, observaciones: string) {
    const form = new FormData(); form.append('archivo', archivo); form.append('referencia', referencia); form.append('observaciones', observaciones);
    return (await apiClient.post<ApiResponse<SolicitudPago>>(`/solicitudes/${id}/pago/comprobantes`, form)).data;
  },
  async download(url: string) {
    if (!/^\/api\/v1\/solicitudes\/\d+\/(?:pago\/comprobantes\/\d+|certificado)$/.test(url)) throw new Error('Enlace de descarga inválido.');
    const response = await apiClient.get<Blob>(url.slice('/api/v1'.length), { responseType: 'blob' });
    const filename = String(response.headers['content-disposition'] ?? '').match(/filename="?([^";]+)"?/i)?.[1] ?? 'documento';
    const objectUrl = URL.createObjectURL(response.data);
    const link = document.createElement('a'); link.href = objectUrl; link.download = filename; link.click(); URL.revokeObjectURL(objectUrl);
  },
};
