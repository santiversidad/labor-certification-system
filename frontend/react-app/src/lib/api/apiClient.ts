import axios from 'axios';
import { env } from '../../config/env';
import { authStorage } from '../auth/authStorage';

export const apiClient = axios.create({
  baseURL: env.apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

export async function initializeCsrf() {
  const url = new URL(env.apiBaseUrl, window.location.origin);
  await apiClient.get(url.origin + '/sanctum/csrf-cookie');
}

apiClient.interceptors.response.use((response) => response, (error) => {
  if (error.response?.status === 401) {
    authStorage.clearSession();
    window.dispatchEvent(new Event('auth-session-expired'));
    const path = window.location.pathname;
    if (path.startsWith('/app') || path.startsWith('/admin') || path.startsWith('/seguridad') || path === '/cambiar-contrasena') {
      window.location.replace('/login?sesion=expirada');
    }
  }
  // 403 leaves identity and caches intact (authorization failures are not logout).
  return Promise.reject(error);
});
