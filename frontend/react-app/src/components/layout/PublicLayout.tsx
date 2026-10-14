import { Outlet } from 'react-router-dom';
import { GovBar } from './GovBar';
import { Footer } from './Footer';
import { Brand } from './Brand';

export function PublicLayout() {
  return (
    <div className="min-h-screen bg-background">
      <GovBar />
      <header className="border-b border-border bg-surface px-4 py-4 sm:px-6"><div className="mx-auto max-w-content"><Brand /></div></header>
      <main className="mx-auto min-h-[calc(100vh-190px)] max-w-4xl px-4 py-8 sm:py-12">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
