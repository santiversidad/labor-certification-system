import { Outlet } from 'react-router-dom';
import { GovBar } from './GovBar';
import { Footer } from './Footer';

export function PublicLayout() {
  return (
    <div className="min-h-screen bg-background">
      <GovBar />
      <main className="mx-auto min-h-[calc(100vh-96px)] max-w-4xl px-4 py-8">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
