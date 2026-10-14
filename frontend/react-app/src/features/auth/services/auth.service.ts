import { apiClient, initializeCsrf } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { ChangePasswordPayload, LoginCredentials, LoginResponse } from '../types/auth.types';

export const authService = {
  async login(credentials: LoginCredentials): Promise<ApiResponse<LoginResponse>> {
    await initializeCsrf();
    const response = await apiClient.post<ApiResponse<LoginResponse>>(
      endpoints.auth.login,
      credentials,
    );
    return response.data;
  },

  async me(): Promise<ApiResponse<LoginResponse['user']>> {
    const response = await apiClient.get<ApiResponse<LoginResponse['user']>>(endpoints.auth.me);
    return response.data;
  },

  async logout(): Promise<void> {
    await apiClient.post(endpoints.auth.logout);
  },
  async setupMfa() {
    return (await apiClient.post<ApiResponse<{ secret: string; issuer: string; account: string; otpauth_uri: string }>>('/auth/mfa/setup')).data;
  },
  async verifyMfa(code: string, enrollment = false) {
    return (await apiClient.post<ApiResponse<{ user: LoginResponse['user']; recovery_codes: string[] }>>('/auth/mfa/' + (enrollment ? 'confirm' : 'verify'), { code })).data;
  },
  async reauthenticate(password: string, code: string) {
    await apiClient.post('/auth/reauthenticate', { password, code });
  },
  async regenerateRecoveryCodes() {
    return (await apiClient.post<ApiResponse<{ recovery_codes: string[] }>>('/auth/mfa/recovery-codes')).data;
  },

  async changePassword(payload: ChangePasswordPayload): Promise<ApiResponse<LoginResponse['user']>> {
    const response = await apiClient.put<ApiResponse<LoginResponse['user']>>(endpoints.auth.changePassword, payload);
    return response.data;
  },
};
