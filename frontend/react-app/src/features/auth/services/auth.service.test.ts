import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '../../../lib/api/apiClient';
import { authService } from './auth.service';

vi.mock('../../../lib/api/apiClient', () => ({
  apiClient: { post: vi.fn(), get: vi.fn(), put: vi.fn() },
}));

describe('authService.changePassword', () => {
  beforeEach(() => vi.clearAllMocks());

  it('envía contraseña actual, nueva y confirmación al endpoint restringido', async () => {
    const payload = {
      current_password: '10000001',
      password: 'ClaveInstitucional#2026',
      password_confirmation: 'ClaveInstitucional#2026',
    };
    vi.mocked(apiClient.put).mockResolvedValue({ data: { success: true, data: { id: 1 } } });

    await authService.changePassword(payload);

    expect(apiClient.put).toHaveBeenCalledWith('/auth/change-password', payload);
  });
});
