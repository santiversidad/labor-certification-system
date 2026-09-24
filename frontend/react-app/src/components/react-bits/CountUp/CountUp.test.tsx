import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CountUp } from './CountUp';

describe('CountUp', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
  });

  it('muestra el valor final sin animación con movimiento reducido', () => {
    vi.stubGlobal('matchMedia', () => ({ matches: true }));
    render(<CountUp to={3115} />);
    expect(screen.getByRole('text', { name: '3.115' })).toHaveTextContent('3.115');
  });

  it('termina en el dato recibido, sin volver a empezar al renderizar', () => {
    vi.useFakeTimers();
    vi.stubGlobal('matchMedia', () => ({ matches: false }));
    const view = render(<CountUp to={344} duration={64} />);
    vi.advanceTimersByTime(96);
    expect(screen.getByRole('text', { name: '344' })).toHaveTextContent('344');
    view.rerender(<CountUp to={344} duration={64} />);
    expect(screen.getByRole('text', { name: '344' })).toHaveTextContent('344');
  });
});
