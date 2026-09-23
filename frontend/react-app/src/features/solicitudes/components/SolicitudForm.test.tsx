import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { SolicitudForm } from './SolicitudForm';
import { solicitudesService } from '../services/solicitudes.service';

vi.mock('../services/solicitudes.service', () => ({ solicitudesService: {
  disponibilidad: vi.fn().mockResolvedValue({ data: { sencillo: { puede_solicitar: true }, funciones: { puede_solicitar: true } } }),
  create: vi.fn().mockImplementation(() => new Promise(() => {})),
} }));

describe('SolicitudForm', () => {
  it('permite solicitar funciones por autoservicio sin enviar funcionario ni ficha elegidos por el cliente', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><MemoryRouter><SolicitudForm /></MemoryRouter></QueryClientProvider>);
    fireEvent.change(screen.getByRole('combobox', { name: 'Contenido de la certificación' }), { target: { value: 'funciones' } });
    await waitFor(() => expect(screen.getByRole('button', { name: 'Continuar' })).toBeEnabled());
    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));
    expect(screen.getByRole('heading', { name: 'Revise y confirme' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar y generar' }));
    await waitFor(() => expect(solicitudesService.create).toHaveBeenCalled());
    const payload = vi.mocked(solicitudesService.create).mock.calls[0][0];
    expect(payload.tipo_certificado).toBe('funciones');
    expect(payload).not.toHaveProperty('requiere_salario');
    expect(payload).not.toHaveProperty('funcionario_id');
    expect(payload).not.toHaveProperty('manual_cargo_version_id');
  });
});
