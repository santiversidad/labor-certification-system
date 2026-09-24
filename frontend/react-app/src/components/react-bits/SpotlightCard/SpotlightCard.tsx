import { useRef, type HTMLAttributes, type PointerEvent } from 'react';
import './SpotlightCard.css';

// Adapted from React Bits FREE SpotlightCard (TS-CSS). The pointer position
// stays local to the card; the effect is supplementary to text and focus.
type Props = HTMLAttributes<HTMLElement> & { disabled?: boolean };

export function SpotlightCard({ children, className = '', disabled = false, ...props }: Props) {
  const ref = useRef<HTMLElement>(null);

  function handlePointerMove(event: PointerEvent<HTMLElement>) {
    if (disabled || event.pointerType !== 'mouse' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    const rect = ref.current?.getBoundingClientRect();
    if (!rect || !ref.current) return;
    ref.current.style.setProperty('--rb-mouse-x', `${event.clientX - rect.left}px`);
    ref.current.style.setProperty('--rb-mouse-y', `${event.clientY - rect.top}px`);
  }

  return <article {...props} className={`rb-spotlight-card ${disabled ? 'rb-spotlight-card--disabled' : ''} ${className}`} onPointerMove={handlePointerMove} ref={ref}>{children}</article>;
}
