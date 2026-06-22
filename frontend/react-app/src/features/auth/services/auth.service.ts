import { apiClient } from '../../../lib/api/apiClient';
import { endpoints } from '../../../lib/api/endpoints';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { LoginCredentials, LoginResponse } from '../types/auth.types';

export const authService = {
  async login(credentials: LoginCredentials): Promise<ApiResponse<LoginResponse>> {
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
    await apiClient.post('/auth/logout');
  },
};
