import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { authService } from '../../features/auth/services/auth.service';
import { Header } from './Header';

const logoutLocal = vi.fn();
vi.mock('../../features/auth/hooks/useAuth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Funcionario', documento: '1', roles: ['funcionario'], permissions: [] },
    logout: logoutLocal,
  }),
}));
vi.mock('../../features/auth/services/auth.service', () => ({
  authService: { logout: vi.fn() },
}));

describe('Header', () => {
  beforeEach(() => vi.clearAllMocks());

  it('llama el logout API antes de cerrar la sesión local', async () => {
    vi.mocked(authService.logout).mockResolvedValue();
    render(<MemoryRouter><Header /></MemoryRouter>);
    fireEvent.click(screen.getByRole('button', { name: 'Salir' }));

    await waitFor(() => expect(authService.logout).toHaveBeenCalledTimes(1));
    expect(logoutLocal).toHaveBeenCalledTimes(1);
  });
});
