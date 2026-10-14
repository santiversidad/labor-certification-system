import { useState } from 'react';
import { Input } from '../../../components/ui/Input';
import { templatesService } from '../services/templates.service';
import { templateError } from '../schemas/template.schema';
import type { TemplateAsset } from '../types/template.types';

export function TemplateAssetUploader({ versionId, slot, asset, disabled, onChange, onUploading }: { versionId: number; slot: 'left' | 'right'; asset?: TemplateAsset; disabled: boolean; onChange: (asset: TemplateAsset) => void; onUploading: (delta: number) => void }) {
  const [source, setSource] = useState(''); const [pending, setPending] = useState(false); const [error, setError] = useState(''); const [local, setLocal] = useState('');
  const label = slot === 'left' ? 'Escudo institucional' : 'Identidad de Secretaría y Dirección de Personal';
  async function upload(file?: File) {
    if (!file) return;
    setError('');
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) { setError('Use PNG, JPEG o WebP de hasta 2 MB.'); return; }
    const reader = new FileReader(); reader.onload = () => setLocal(String(reader.result)); reader.readAsDataURL(file);
    setPending(true); onUploading(1);
    try { const normalized = await templatesService.upload(versionId, slot, file, source); onChange(normalized); setSource(''); }
    catch (e) { setError(await templateError(e)); }
    finally { setPending(false); setLocal(''); onUploading(-1); }
  }
  return <section className="min-w-0 space-y-3 border-b border-border py-4"><h3 className="text-sm font-semibold">{label}</h3><div className="flex h-28 items-center justify-center rounded-md border border-border bg-white p-3">{local || asset ? <img src={local || asset?.url} alt={label} className="h-full w-full object-contain" /> : <span className="text-sm text-muted">Imagen pendiente</span>}</div>
    {asset && <p className="break-words text-xs text-muted">Imagen validada · {asset.ancho} × {asset.alto} px. {asset.procedencia}</p>}
    {!disabled && <><Input label={`Procedencia de la nueva imagen ${slot === 'left' ? 'izquierda' : 'derecha'}`} maxLength={250} value={source} onChange={(e) => setSource(e.target.value)} disabled={pending} /><Input label={`Reemplazar ${slot === 'left' ? 'escudo' : 'imagen derecha'}`} type="file" accept="image/png,image/jpeg,image/webp" disabled={pending || !source.trim()} onChange={(e) => { void upload(e.target.files?.[0]); e.target.value = ''; }} /><p className="text-xs text-muted">Máximo 2 MB. Se conserva la proporción. Use identidad institucional, sin firmas personales.</p></>}
    {pending && <p role="status" className="text-sm">Validando imagen…</p>}{error && <p role="alert" className="text-sm text-error">{error}</p>}
  </section>;
}
