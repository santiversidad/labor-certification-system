import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Alert } from './Alert';

describe('Alert', () => {
  for (const variant of ['info', 'warning', 'success', 'error'] as const) {
    it(`muestra título y descripción de ${variant} con icono decorativo`, () => {
      const { container } = render(<Alert title="Aviso" variant={variant}>Descripción útil</Alert>);
      expect(screen.getByText('Aviso')).toBeVisible();
      expect(screen.getByText('Descripción útil')).toBeVisible();
      expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
      expect(container.firstChild).toHaveClass('border');
      if (variant === 'error') expect(container.firstChild).toHaveAttribute('role', 'alert');
      else expect(container.firstChild).not.toHaveAttribute('role');
    });
  }
});
