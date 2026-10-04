import axios from 'axios';
import { beforeEach, expect, it, vi } from 'vitest';
import { apiClient, initializeCsrf } from './apiClient';
import { authStorage } from '../auth/authStorage';

beforeEach(() => { authStorage.clearSession(); vi.restoreAllMocks(); });
it('enables credentials and official Axios XSRF support without bearer', async () => {
  const adapter = vi.fn(async (config) => ({ data: {}, status: 200, statusText: 'OK', headers: {}, config }));
  await apiClient.get('/auth/me', { adapter });
  const config = adapter.mock.calls[0][0];
  expect(config.withCredentials).toBe(true); expect(config.withXSRFToken).toBe(true);
  expect(config.headers.Authorization).toBeUndefined();
});
it('CSRF bootstrap goes to the API origin using the same credentialed client', async () => {
  const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ status: 204 });
  await initializeCsrf(); expect(get).toHaveBeenCalledWith(expect.stringContaining('/sanctum/csrf-cookie'));
});
it('401 emits identity cleanup while 403 does not emit logout', async () => {
  const clear = vi.spyOn(authStorage, 'clearSession');
  const fail = (status: number) => async (config: object) => { throw new axios.AxiosError('test', '', config as never, null, { status, data: {}, headers: {}, statusText: '', config } as never); };
  await expect(apiClient.get('/test', { adapter: fail(403) })).rejects.toBeDefined(); expect(clear).not.toHaveBeenCalled();
  await expect(apiClient.get('/test', { adapter: fail(401) })).rejects.toBeDefined(); expect(clear).toHaveBeenCalledOnce();
});
