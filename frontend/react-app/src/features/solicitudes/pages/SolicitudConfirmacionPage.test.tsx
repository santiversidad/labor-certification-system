import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { SolicitudConfirmacionPage } from './SolicitudConfirmacionPage';

describe('SolicitudConfirmacionPage', () => {
  it('muestra el radicado real recibido del backend', () => {
    render(
      <MemoryRouter initialEntries={[{
        pathname: '/app/solicitudes/confirmacion',
        state: {
          resultado: 'generada',
          solicitud: {
            id: 123,
            radicado: 'CL-2026-000123',
            tipo_certificado: 'laboral',
            estado: 'generada',
            requiere_pago: false,
            requiere_salario: false,
            periodo_mes: '2026-08-01',
          },
          descarga_url: '/api/v1/mi-certificado/descargar/token-seguro',
        },
      }]}>
        <Routes>
          <Route path="/app/solicitudes/confirmacion" element={<SolicitudConfirmacionPage />} />
        </Routes>
      </MemoryRouter>,
    );

    expect(screen.getByText('CL-2026-000123')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Descargar PDF' })).toBeInTheDocument();
    expect(screen.queryByText('Pendiente de confirmacion del backend')).not.toBeInTheDocument();
  });

  it('muestra orden pendiente sin ofrecer descarga cuando el pago está activo', () => {
    render(
      <MemoryRouter initialEntries={[{
        pathname: '/app/solicitudes/confirmacion',
        state: {
          resultado: 'pendiente_pago',
          solicitud: {
            id: 124,
            radicado: 'CL-2026-000124',
            tipo_certificado: 'laboral',
            estado: 'pendiente_pago',
            requiere_pago: true,
            requiere_salario: true,
            periodo_mes: '2026-08-01',
          },
          orden_pago: { referencia: 'PAGO-ABC-124', estado: 'pendiente' },
        },
      }]}>
        <Routes><Route path="/app/solicitudes/confirmacion" element={<SolicitudConfirmacionPage />} /></Routes>
      </MemoryRouter>,
    );

    expect(screen.getByText('Pendiente de confirmación de pago')).toBeInTheDocument();
    expect(screen.getByText(/PAGO-ABC-124/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Descargar PDF' })).not.toBeInTheDocument();
  });
});
