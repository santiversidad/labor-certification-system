export function GovBar() {
  return (
    <div className="flex min-h-9 items-center justify-between bg-[#0943b5] px-4 py-1.5 text-white sm:px-6">
      <a aria-label="Ir al Portal Único del Estado Colombiano GOV.CO" className="text-sm font-extrabold tracking-tight hover:underline" href="https://www.gov.co/" rel="noreferrer" target="_blank">GOV.CO</a>
      <span className="text-xs font-medium text-white/80">Portal institucional</span>
    </div>
  );
}
