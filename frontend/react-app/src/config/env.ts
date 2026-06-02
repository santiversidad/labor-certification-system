export const env = {
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1',
  useMocks: (import.meta.env.VITE_USE_MOCKS ?? 'true') === 'true',
  appName: import.meta.env.VITE_APP_NAME ?? 'Certificaciones Laborales Villavicencio',
};
