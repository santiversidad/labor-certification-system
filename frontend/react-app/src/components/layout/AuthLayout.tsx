import { Outlet } from 'react-router-dom';
import { CheckCircle2, FileCheck2, ShieldCheck } from 'lucide-react';
import { Brand } from './Brand';
import { GovBar } from './GovBar';
import { Footer } from './Footer';

export function AuthLayout() {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <GovBar />
      <main className="grid flex-1 lg:grid-cols-[minmax(0,1.05fr)_minmax(430px,0.95fr)]">
        <section className="relative hidden overflow-hidden bg-[#06285e] px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16">
          <div aria-hidden="true" className="absolute -right-24 top-28 h-72 w-72 rounded-full border border-white/10" />
          <div aria-hidden="true" className="absolute -right-4 top-52 h-40 w-40 rounded-full border border-white/10" />
          <Brand inverse />
          <div className="relative max-w-xl animate-fade-up">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-blue-200">Servicio institucional</p>
            <h1 className="mt-4 text-4xl font-bold leading-tight tracking-[-0.035em] xl:text-5xl">Su certificación laboral, cuando la necesite.</h1>
            <p className="mt-5 max-w-lg text-base leading-7 text-blue-100/85">Consulta, solicita y descarga documentos oficiales en un proceso seguro y completamente digital.</p>
            <ul className="mt-9 grid gap-4 text-sm text-blue-50 sm:grid-cols-3">
              <li className="flex items-center gap-2"><FileCheck2 size={18} /> Solicitud simple</li>
              <li className="flex items-center gap-2"><CheckCircle2 size={18} /> Generación inmediata</li>
              <li className="flex items-center gap-2"><ShieldCheck size={18} /> Validación pública</li>
            </ul>
          </div>
          <p className="relative text-xs text-blue-200/75">Alcaldía de Villavicencio · Meta</p>
        </section>
        <section className="flex items-center justify-center px-4 py-10 sm:px-8 lg:px-12">
          <div className="w-full max-w-md animate-fade-up">
            <div className="mb-8 lg:hidden"><Brand /></div>
            <Outlet />
          </div>
        </section>
      </main>
      <div className="lg:hidden"><Footer /></div>
    </div>
  );
}
