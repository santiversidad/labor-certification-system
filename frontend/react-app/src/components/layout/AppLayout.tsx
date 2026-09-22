import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Footer } from './Footer';
import { GovBar } from './GovBar';
import { Header } from './Header';
import { Sidebar } from './Sidebar';

type AppLayoutProps = {
  variant?: 'app' | 'admin';
};

export function AppLayout({ variant = 'app' }: AppLayoutProps) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background">
      <GovBar />
      <div className="flex min-h-[calc(100vh-36px)]">
        {variant === 'admin' ? <Sidebar mobileOpen={mobileMenuOpen} onClose={() => setMobileMenuOpen(false)} variant={variant} /> : null}
        <div className="flex min-w-0 flex-1 flex-col">
          <Header onMenuClick={variant === 'admin' ? () => setMobileMenuOpen(true) : undefined} variant={variant} />
          <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <Outlet />
          </main>
          <Footer />
        </div>
      </div>
    </div>
  );
}
