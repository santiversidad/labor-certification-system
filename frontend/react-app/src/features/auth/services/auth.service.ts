import { env } from '../../../config/env';
import { endpoints } from '../../../lib/api/endpoints';
import { apiClient } from '../../../lib/api/apiClient';
import { mockResponse } from '../../../lib/api/mockAdapter';
import { mockUsers } from '../../../lib/mocks/mockUsers';
import type { ApiResponse } from '../../../lib/api/api.types';
import type { LoginCredentials, LoginResponse } from '../types/auth.types';

export const authService = {
  async login(credentials: LoginCredentials): Promise<ApiResponse<LoginResponse>> {
    if (env.useMocks) {
      const user = mockUsers.find((item) => item.email.toLowerCase() === credentials.email.toLowerCase());

      if (!user) {
        throw new Error('Usuario mock no encontrado.');
      }

      return mockResponse({
        token: `mock-token-${user.role}`,
        user,
      });
    }

    const response = await apiClient.post<ApiResponse<LoginResponse>>(endpoints.auth.login, credentials);
    return response.data;
  },
};
