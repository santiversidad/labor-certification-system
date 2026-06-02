import { Outlet } from 'react-router-dom';
import { env } from '../../config/env';
import { GovBar } from './GovBar';

export function AuthLayout() {
  return (
    <div className="min-h-screen bg-background">
      <GovBar />
      <main className="mx-auto flex min-h-[calc(100vh-40px)] max-w-md items-center px-4 py-10">
        <div className="w-full">
          <div className="mb-6">
            <h1 className="text-2xl font-semibold text-text">{env.appName}</h1>
            <p className="mt-2 text-sm text-muted">Acceso institucional para gestion de certificaciones laborales.</p>
          </div>
          <Outlet />
        </div>
      </main>
    </div>
  );
}
