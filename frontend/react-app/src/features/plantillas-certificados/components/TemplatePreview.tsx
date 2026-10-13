import { useEffect, useRef, useState } from 'react';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';
import { Button } from '../../../components/ui/Button';

export function TemplatePreview({ blob }: { blob: Blob }) {
  const [pdf, setPdf] = useState<PDFDocumentProxy | null>(null); const [page, setPage] = useState(1); const [error, setError] = useState(''); const [url, setUrl] = useState('');
  const [renderedKey, setRenderedKey] = useState('');
  const pageKey = pdf ? `${pdf.fingerprints[0]}:${page}` : '';
  const container = useRef<HTMLDivElement>(null); const canvas = useRef<HTMLCanvasElement>(null);
  useEffect(() => {
    let cancelled = false; let dispose: (() => void) | undefined;
    const download = URL.createObjectURL(blob);
    void (async () => {
      try {
        const lib = await import('pdfjs-dist/legacy/build/pdf.mjs'); lib.GlobalWorkerOptions.workerSrc = workerUrl;
        const task = lib.getDocument({ data: new Uint8Array(await blob.arrayBuffer()), disableFontFace: true, useSystemFonts: true, useWasm: false });
        dispose = () => { void task.destroy(); };
        const document = await task.promise;
        if (!cancelled) { setPdf(document); setPage(1); setError(''); setUrl(download); }
        else dispose();
      } catch { if (!cancelled) { setUrl(download); setError('No se pudo mostrar el PDF. Puede descargarlo para revisarlo.'); } }
    })();
    return () => { cancelled = true; dispose?.(); URL.revokeObjectURL(download); };
  }, [blob]);
  useEffect(() => {
    if (!pdf || !container.current || !canvas.current) return;
    let cancelled = false; let rendering: { cancel: () => void; promise: Promise<unknown> } | undefined;
    let drawId = 0;
    const element = container.current; const target = canvas.current;
    async function draw() {
      const current = ++drawId;
      const previous = rendering;
      rendering?.cancel();
      try {
        await previous?.promise.catch(() => undefined);
        const documentPage = await pdf!.getPage(page); if (cancelled || current !== drawId) return;
        const width = Math.max(100, Math.min(720, element.clientWidth - 24)); const normal = documentPage.getViewport({ scale: 1 });
        const viewport = documentPage.getViewport({ scale: width / normal.width });
        target.width = Math.ceil(viewport.width); target.height = Math.ceil(viewport.height);
        rendering = documentPage.render({ canvas: target, viewport }); await rendering.promise;
        if (!cancelled && current === drawId) setRenderedKey(`${pdf!.fingerprints[0]}:${page}`);
      } catch (e) { if (!cancelled && !(e instanceof Error && e.name === 'RenderingCancelledException')) setError('No se pudo dibujar esta página del PDF.'); }
    }
    const observer = new ResizeObserver(() => { void draw(); }); observer.observe(element); void draw();
    return () => { cancelled = true; observer.disconnect(); rendering?.cancel(); };
  }, [pdf, page]);
  return <section aria-label="Vista previa del certificado" className="min-w-0 space-y-3">
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm"><p role="status">{pdf ? `Página ${page} de ${pdf.numPages}` : 'Preparando PDF…'}</p>{url && <a className="font-semibold text-primary underline" href={url} download="vista-previa-borrador.pdf">Descargar vista previa</a>}</div>
    {error && <p role="alert" className="text-sm text-error">{error}</p>}
    <div ref={container} className="flex min-h-64 min-w-0 justify-center overflow-hidden rounded-md bg-slate-100 p-3"><canvas ref={canvas} className="h-auto max-w-full self-start bg-white shadow-sm" role="img" aria-busy={!pageKey || renderedKey !== pageKey} aria-label={`PDF sintético, página ${page}${pdf ? ` de ${pdf.numPages}` : ''}`} /></div>
    {pdf && pdf.numPages > 1 && <div className="flex flex-wrap justify-between gap-2"><Button type="button" variant="secondary" disabled={page === 1} onClick={() => setPage(page - 1)}>Página anterior</Button><Button type="button" variant="secondary" disabled={page === pdf.numPages} onClick={() => setPage(page + 1)}>Página siguiente</Button></div>}
    <p className="text-xs text-muted">PDF real generado con datos sintéticos. BORRADOR — SIN VALIDEZ OFICIAL.</p>
  </section>;
}
