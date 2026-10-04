import { useState } from 'react';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { formatDate } from '../../../lib/formatters/dates';
import { getErrorMessage } from '../../../lib/utils/errors';
import type { ComprobantePago } from '../../solicitudes/types/solicitud.types';
import { paymentWorkflowService } from '../services/paymentWorkflow.service';

export function ReceiptHistory({ comprobantes }: { comprobantes: ComprobantePago[] }) {
  const [error, setError] = useState('');
  const [downloading, setDownloading] = useState<number | null>(null);
  async function descargar(c: ComprobantePago) {
    setError(''); setDownloading(c.id);
    try { await paymentWorkflowService.download(c.archivo_url); } catch (cause) { setError(getErrorMessage(cause)); } finally { setDownloading(null); }
  }
  return <section className="surface-section p-5 sm:p-7"><h2 className="text-lg font-bold">Historial de comprobantes</h2>
    {error ? <Alert className="mt-4" variant="error" title="No fue posible descargar">{error}</Alert> : null}
    {comprobantes.length === 0 ? <p className="mt-3 text-sm text-muted">Todavía no ha adjuntado comprobantes.</p> : <ol className="mt-4 divide-y divide-border">{comprobantes.map((c) => <li className="space-y-2 py-4" key={c.id}>
      <div className="flex flex-wrap items-center justify-between gap-3"><h3 className="font-semibold">Comprobante {c.version} — {c.estado === 'en_revision' ? 'En revisión' : c.estado === 'confirmado' ? 'Pago confirmado' : 'Pago rechazado'}</h3><Button disabled={downloading !== null} onClick={() => void descargar(c)} type="button" variant="secondary">{downloading === c.id ? 'Descargando...' : `Ver comprobante ${c.version}`}</Button></div>
      <p className="break-words text-sm">{c.nombre_original} · {Math.ceil(c.size_bytes / 1024)} KB · {c.created_at ? formatDate(c.created_at) : '—'}</p>
      {c.referencia ? <p className="break-words text-sm">Referencia declarada: {c.referencia}</p> : null}
      {c.observacion_funcionario ? <p className="break-words text-sm">Observación del titular: {c.observacion_funcionario}</p> : null}
      {c.revisado_at ? <p className="text-sm text-muted">Revisión: {formatDate(c.revisado_at)} · {c.revisor?.name ?? 'Revisor registrado'}</p> : null}
      {c.motivo_rechazo ? <p className="whitespace-pre-wrap break-words text-sm text-error">Motivo: {c.motivo_rechazo}</p> : null}
    </li>)}</ol>}
  </section>;
}
