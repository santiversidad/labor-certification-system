import type { ReactNode } from 'react';
import './GradientText.css';

// Adapted from React Bits FREE GradientText (TS-CSS): static institutional
// palette and a single CSS sweep replace upstream's continuous Motion loop.
export function GradientText({ children }: { children: ReactNode }) {
  return <span className="rb-gradient-text">{children}</span>;
}
