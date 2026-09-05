import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { configuracionService } from './configuracion.service';

vi.mock('../../../lib/api/apiClient', () => ({
  apiClient: { get: vi.fn(), patch: vi.fn() },
}));

describe('configuracionService', () => {
  beforeEach(() => vi.clearAllMocks());

  it('actualiza exclusivamente el interruptor institucional de pago', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { success: true, data: { requiere_pago_certificado: true, descripcion: 'Pago activo' } },
    });

    await configuracionService.update(true);

    expect(apiClient.patch).toHaveBeenCalledWith('/configuracion/certificaciones', {
      requiere_pago_certificado: true,
    });
  });
});
