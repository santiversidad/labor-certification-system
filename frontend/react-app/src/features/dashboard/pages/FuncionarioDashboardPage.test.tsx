import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import { FuncionarioDashboardPage } from './FuncionarioDashboardPage';

vi.mock('../../solicitudes/services/solicitudes.service', () => ({
  solicitudesService: { disponibilidad: vi.fn() },
}));

function renderDashboard() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter><FuncionarioDashboardPage /></MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('FuncionarioDashboardPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('muestra los tipos sencillo y funciones', async () => {
    vi.mocked(solicitudesService.disponibilidad).mockResolvedValue({
      success: true,
      data: {
        periodo: '2026-08-01',
        sencillo: { puede_solicitar: true, proxima_fecha_disponible: null },
        funciones: { puede_solicitar: true, proxima_fecha_disponible: null },
      },
    });
    renderDashboard();

    expect(await screen.findByText('Certificado laboral sencillo')).toBeInTheDocument();
    expect(screen.getByText('Certificado laboral con funciones')).toBeInTheDocument();
    expect(screen.queryByText(/salario/i)).not.toBeInTheDocument();
  });

  it('bloquea únicamente la modalidad consumida', async () => {
    vi.mocked(solicitudesService.disponibilidad).mockResolvedValue({
      success: true,
      data: {
        periodo: '2026-08-01',
        sencillo: { puede_solicitar: false, proxima_fecha_disponible: '2026-09-01' },
        funciones: { puede_solicitar: true, proxima_fecha_disponible: null },
      },
    });
    renderDashboard();

    const botones = await screen.findAllByRole('button', { name: 'Solicitar' });
    expect(botones[0]).toBeDisabled();
    expect(botones[1]).toBeEnabled();
  });
});
