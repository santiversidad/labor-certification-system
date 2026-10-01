import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SolicitudCertificadoPage } from '../pages/SolicitudCertificadoPage';
import { solicitudesService } from '../services/solicitudes.service';
import type { TipoCertificado } from '../types/solicitud.types';

vi.mock('../services/solicitudes.service', () => ({ solicitudesService: {
  disponibilidad: vi.fn(),
  create: vi.fn().mockImplementation(() => new Promise(() => {})),
} }));

function renderRoute(tipo: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[`/app/solicitudes/nueva/${tipo}`]}><Routes><Route element={<SolicitudCertificadoPage />} path="/app/solicitudes/nueva/:tipo" /><Route element={<div>Inicio</div>} path="/app/inicio" /></Routes></MemoryRouter></QueryClientProvider>);
}

function availability(sencillo: boolean, funciones: boolean) {
  vi.mocked(solicitudesService.disponibilidad).mockResolvedValue({ success: true, data: { periodo: '2026-09-01', sencillo: { puede_solicitar: sencillo, proxima_fecha_disponible: sencillo ? null : '2026-10-01' }, funciones: { puede_solicitar: funciones, proxima_fecha_disponible: funciones ? null : '2026-10-01' } } });
}

describe('SolicitudCertificadoPage', () => {
  beforeEach(() => { vi.clearAllMocks(); vi.mocked(solicitudesService.create).mockImplementation(() => new Promise(() => {})); });

  for (const tipo of ['sencillo', 'funciones'] as TipoCertificado[]) {
    it(`fija ${tipo} desde la ruta y construye el payload sin selector`, async () => {
      availability(true, true);
      renderRoute(tipo);
      expect(screen.queryByRole('radio')).not.toBeInTheDocument();
      expect(screen.queryByRole('combobox', { name: /tipo/i })).not.toBeInTheDocument();
      expect(screen.getByText('Tipo seleccionado')).toBeVisible();
      expect(screen.getByRole('heading', { name: tipo === 'sencillo' ? 'Certificado laboral sencillo' : 'Certificado laboral con funciones' })).toBeVisible();
      const button = await screen.findByRole('button', { name: tipo === 'sencillo' ? 'Generar certificado sencillo' : 'Generar certificado con funciones' });
      await waitFor(() => expect(button).toBeEnabled());
      fireEvent.click(button);
      await waitFor(() => expect(solicitudesService.create).toHaveBeenCalled());
      expect(vi.mocked(solicitudesService.create).mock.calls[0][0]).toEqual({ tipo_certificado: tipo, observaciones: '' });
      const payload = vi.mocked(solicitudesService.create).mock.calls[0][0];
      expect(payload).not.toHaveProperty('requiere_salario');
      expect(payload).not.toHaveProperty('funcionario_id');
      expect(payload).not.toHaveProperty('manual_ficha_id');
      expect(payload).not.toHaveProperty('incluye_funciones');
    });
  }

  it('redirige un parámetro inválido y no envía solicitud', () => {
    renderRoute('xyz');
    expect(screen.getByText('Inicio')).toBeVisible();
    expect(solicitudesService.create).not.toHaveBeenCalled();
    expect(solicitudesService.disponibilidad).not.toHaveBeenCalled();
  });

  it('bloquea un enlace directo sin disponibilidad y destaca la fecha', async () => {
    availability(false, true);
    renderRoute('sencillo');
    expect(await screen.findByText('Certificado no disponible actualmente')).toBeVisible();
    expect(screen.getByText('1 de octubre de 2026')).toBeVisible();
    expect(screen.getByRole('button', { name: 'Generar certificado sencillo' })).toBeDisabled();
    expect(solicitudesService.create).not.toHaveBeenCalled();
  });

  it('presenta un error 409 del backend como aviso', async () => {
    availability(true, true);
    vi.mocked(solicitudesService.create).mockRejectedValueOnce({ response: { status: 409, data: { message: 'Ficha del Manual incompleta', code: 'MANUAL_FICHA_INCOMPLETA' } } });
    renderRoute('funciones');
    const button = await screen.findByRole('button', { name: 'Generar certificado con funciones' });
    await waitFor(() => expect(button).toBeEnabled());
    fireEvent.click(button);
    expect(await screen.findByRole('alert')).toHaveTextContent('La ficha del Manual de Funciones está incompleta');
  });
});
