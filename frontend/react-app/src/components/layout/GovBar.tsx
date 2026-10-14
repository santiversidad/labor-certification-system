export function GovBar() {
  return (
    <div className="flex min-h-9 items-center justify-between bg-[#0943b5] px-4 py-1.5 text-white sm:px-6">
      <a aria-label="Ir al Portal Único del Estado Colombiano GOV.CO" className="inline-flex shrink-0 items-center rounded-sm bg-white px-2 py-1" href="https://www.gov.co/" rel="noopener noreferrer" target="_blank">
        <img alt="" className="h-auto w-[88px]" height={323} src="/assets/brand/govco.png" width={1600} />
      </a>
      <span className="text-xs font-medium text-white/80">Portal institucional</span>
    </div>
  );
}
