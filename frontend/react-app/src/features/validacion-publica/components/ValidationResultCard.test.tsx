import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ValidationResultCard } from './ValidationResultCard';

describe('ValidationResultCard', () => {
  it('muestra certificado válido con datos públicos', () => {
    render(<ValidationResultCard result={{
      valido: true,
      resultado: 'valido',
      estado: 'vigente',
      codigo_unico: 'CL-2026-ABC',
      fecha_generacion: '2026-08-30',
      funcionario: { nombre: 'Ana Pérez' },
      cargo: 'Profesional Universitario',
      mensaje: 'Certificado válido.',
    }} />);
    expect(screen.getByText('Válido')).toBeInTheDocument();
    expect(screen.getByText('Ana Pérez')).toBeInTheDocument();
    expect(screen.getByText('Profesional Universitario')).toBeInTheDocument();
  });

  it('muestra el mensaje exacto para código inválido', () => {
    render(<ValidationResultCard result={{ valido: false, resultado: 'no_encontrado' }} />);
    expect(screen.getByText('Certificado no encontrado o código de validación inválido.')).toBeInTheDocument();
  });

  it('distingue explícitamente un certificado anulado', () => {
    render(<ValidationResultCard result={{ valido: false, resultado: 'anulado', estado: 'anulado' }} />);
    expect(screen.getAllByText('CERTIFICADO ANULADO')).toHaveLength(2);
  });
});
