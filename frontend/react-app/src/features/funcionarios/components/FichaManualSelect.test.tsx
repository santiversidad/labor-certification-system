import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FichaManualSelect, type FichaManual } from './FichaManualSelect';

const ficha = (id: number, area: string): FichaManual => ({ id, source_id: `MF-${id}`, denominacion: 'Técnico Administrativo', codigo: '367', grado: '05', dependencia: 'Donde se ubique el cargo', area_funcional: area, proposito_principal: `Propósito ${area}`, version: 'Decreto 015 de 2023', estado: 'publicado', vigente: true });

describe('FichaManualSelect', () => {
  it('distingue fichas por área y exige selección explícita entre varias', () => {
    const onChange = vi.fn();
    render(<FichaManualSelect fichas={[ficha(228, 'Oficina Jurídica'), ficha(229, 'Gestión del Riesgo')]} value="" onChange={onChange} />);
    expect(screen.getByRole('combobox')).toHaveValue('');
    expect(onChange).not.toHaveBeenCalled();
    expect(screen.getByRole('option', { name: /Gestión del Riesgo/ })).toBeInTheDocument();
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '229' } });
    expect(onChange).toHaveBeenCalledWith('229');
  });
  it('informa ausencia de fichas', () => {
    render(<FichaManualSelect fichas={[]} value="" onChange={vi.fn()} />);
    expect(screen.getByText(/No hay fichas/)).toBeInTheDocument();
  });
  it('conserva selección existente y explica propósito y estado borrador', () => {
    render(<FichaManualSelect fichas={[{ ...ficha(229, 'Riesgo'), estado: 'borrador', vigente: false }]} value="229" onChange={vi.fn()} />);
    expect(screen.getByRole('combobox')).toHaveValue('229');
    expect(screen.getByText(/aún no habilita/)).toHaveTextContent('Propósito Riesgo');
  });
});
