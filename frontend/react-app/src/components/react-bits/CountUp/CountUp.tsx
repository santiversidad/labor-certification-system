import { useLayoutEffect, useRef } from 'react';

// Adapted from React Bits FREE CountUp (TS-CSS). Keeps the upstream number
// presentation concept while replacing Motion's spring with one short timer.
export function CountUp({ to, duration = 520 }: { to: number; duration?: number }) {
  const visual = useRef<HTMLSpanElement>(null);
  const animated = useRef(false);
  const formatted = new Intl.NumberFormat('es-CO').format(to);

  useLayoutEffect(() => {
    const node = visual.current;
    if (!node) return;
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    if (animated.current || reduce || to <= 0) {
      node.textContent = formatted;
      animated.current = true;
      return;
    }
    animated.current = true;
    const started = performance.now();
    node.textContent = '0';
    const timer = window.setInterval(() => {
      const progress = Math.min((performance.now() - started) / duration, 1);
      const eased = 1 - (1 - progress) ** 3;
      node.textContent = new Intl.NumberFormat('es-CO').format(Math.round(to * eased));
      if (progress === 1) window.clearInterval(timer);
    }, 32);
    return () => {
      window.clearInterval(timer);
      node.textContent = formatted;
    };
  }, [duration, formatted, to]);

  return <span aria-label={formatted} role="text"><span aria-hidden="true" ref={visual}>{formatted}</span></span>;
}
