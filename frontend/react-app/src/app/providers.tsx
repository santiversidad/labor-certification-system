import type { ReactNode } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '../components/feedback/ToastProvider';
import { queryClient } from './queryClient';

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      <ToastProvider>{children}</ToastProvider>
    </QueryClientProvider>
  );
}
