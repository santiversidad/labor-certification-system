import { beforeEach, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { solicitudesService } from './solicitudes.service';

vi.mock('../../../lib/api/apiClient', () => ({ apiClient: { get: vi.fn() } }));
beforeEach(() => vi.clearAllMocks());

it('descarga inmediata elimina el prefijo API duplicado y conserva el blob PDF', async () => {
  const token = 'a'.repeat(64);
  const blob = new Blob(['%PDF-1.4'], { type: 'application/pdf' });
  vi.mocked(apiClient.get).mockResolvedValue({ data: blob, headers: { 'content-disposition': 'attachment; filename="CL-2026.pdf"' } });
  const response = await solicitudesService.downloadImmediate(`/api/v1/mi-certificacion/descargar/${token}`);
  expect(apiClient.get).toHaveBeenCalledWith(`/mi-certificacion/descargar/${token}`, { responseType: 'blob' });
  expect(response).toEqual({ blob, filename: 'CL-2026.pdf' });
});

it('no envía credenciales a una URL externa de descarga', async () => {
  await expect(solicitudesService.downloadImmediate('https://otro.example/archivo')).rejects.toThrow('no es válido');
  expect(apiClient.get).not.toHaveBeenCalled();
});
