import axios from 'axios';
import { env } from '../../config/env';
import { authStorage } from '../auth/authStorage';

export const apiClient = axios.create({
  baseURL: env.apiBaseUrl,
  headers: {
    Accept: 'application/json',
  },
});

// Adjunta automáticamente el token Bearer en cada petición
apiClient.interceptors.request.use((config) => {
  const token = authStorage.getToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// Maneja sesiones expiradas: si el backend responde 401, limpia la sesión
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const path = window.location.pathname;

      // No purgar la sesión durante el propio login (evita bucle)
      if (!path.includes('/login')) {
        authStorage.clearSession();

        // Redirigir al login solo si el usuario estaba en una zona privada
        if (path.startsWith('/app') || path.startsWith('/admin')) {
          window.location.href = '/login';
        }
      }
    }
    return Promise.reject(error);
  },
);
