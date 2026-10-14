import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { certificadosService } from './certificados.service';

vi.mock('../../../lib/api/apiClient', () => ({
  apiClient: { get: vi.fn(), patch: vi.fn() },
}));

describe('certificadosService.download', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn(() => 'blob:certificado'),
      revokeObjectURL: vi.fn(),
    });
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
  });

  it('usa el endpoint protegido como blob, descarga y revoca el Object URL', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: new Blob(['pdf'], { type: 'application/pdf' }),
      headers: { 'content-disposition': 'attachment; filename="CL-2026-ABC.pdf"' },
    });

    await expect(certificadosService.download(42)).resolves.toBe('CL-2026-ABC.pdf');
    expect(apiClient.get).toHaveBeenCalledWith('/certificados/42/descargar', { responseType: 'blob' });
    expect(URL.createObjectURL).toHaveBeenCalledTimes(1);
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:certificado');
  });
});
