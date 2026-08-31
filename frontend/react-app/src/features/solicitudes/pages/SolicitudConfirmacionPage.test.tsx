import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { SolicitudConfirmacionPage } from './SolicitudConfirmacionPage';

describe('SolicitudConfirmacionPage', () => {
  it('muestra el radicado real recibido del backend', () => {
    render(
      <MemoryRouter initialEntries={[{
        pathname: '/app/solicitudes/confirmacion',
        state: { radicado: 'CL-2026-000123', requiereSalario: false, fechaRadicacion: '2026-08-30T10:00:00Z' },
      }]}>
        <Routes>
          <Route path="/app/solicitudes/confirmacion" element={<SolicitudConfirmacionPage />} />
        </Routes>
      </MemoryRouter>,
    );

    expect(screen.getByText('CL-2026-000123')).toBeInTheDocument();
    expect(screen.queryByText('Pendiente de confirmacion del backend')).not.toBeInTheDocument();
  });
});
