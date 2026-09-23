import { CheckCircle, Download, Home, Hourglass } from 'lucide-react';
import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Badge } from '../../../components/ui/Badge';
import { getErrorMessage } from '../../../lib/utils/errors';
import { formatDate } from '../../../lib/formatters/dates';
import { solicitudesService } from '../services/solicitudes.service';
import type { ExpedicionCertificacion } from '../types/solicitud.types';
import { certificateTypeLabel } from '../utils/certificateType';

export function SolicitudConfirmacionPage() {
  const { state } = useLocation();
  const resultado = state as ExpedicionCertificacion | null;
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  if (!resultado) return <Navigate replace to="/app/inicio" />;
  const generada = resultado.resultado === 'generada';

  async function descargar() {
    if (!resultado?.descarga_url) return;
    setDownloading(true); setError(null);
    try {
      const { blob, filename } = await solicitudesService.downloadImmediate(resultado.descarga_url);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a'); link.href = url; link.download = filename; link.click();
      URL.revokeObjectURL(url);
    } catch (cause) { setError(getErrorMessage(cause)); } finally { setDownloading(false); }
  }

  return (
    <div className="mx-auto max-w-2xl rounded-lg border border-border bg-surface p-6 shadow-soft sm:p-9">
      <div className="flex gap-4">
        {generada ? <CheckCircle className="shrink-0 text-success" size={36} /> : <Hourglass className="shrink-0 text-warning" size={36} />}
        <div className="min-w-0 flex-1">
          <p className="eyebrow">Paso 4 de 4 · Resultado</p>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-text">{generada ? 'Certificación generada correctamente' : 'Pendiente de confirmación de pago'}</h1>
          <p className="mt-3 text-sm leading-6 text-muted">{generada ? 'El documento está listo. Descárguelo ahora; el enlace inmediato tiene vigencia limitada.' : 'La certificación se generará automáticamente cuando una futura pasarela confirme el pago al backend.'}</p>
          <dl className="mt-6 divide-y divide-border rounded-lg border border-border text-sm">
            <ResultRow label="Radicado" value={resultado.solicitud.radicado} />
            <ResultRow label="Fecha" value={resultado.solicitud.created_at ? formatDate(resultado.solicitud.created_at) : 'Generado ahora'} />
            <ResultRow label="Tipo" value={certificateTypeLabel(resultado.solicitud.tipo_certificado)} />
            <div className="grid gap-1 px-4 py-3 sm:grid-cols-[120px_1fr]"><dt className="text-muted">Estado</dt><dd><Badge tone={generada ? 'green' : 'gold'}>{generada ? 'Disponible' : 'Requiere pago'}</Badge></dd></div>
          </dl>
          {resultado.orden_pago ? <p className="mt-4 rounded-lg bg-surface-muted p-3 text-sm text-text">Referencia de pago: <strong>{resultado.orden_pago.referencia}</strong></p> : null}
          {error ? <p className="mt-4 text-sm text-error" role="alert">{error}</p> : null}
          <div className="mt-7 flex flex-wrap gap-3">
            {generada ? <Button disabled={downloading} icon={<Download size={17} />} onClick={descargar} type="button">{downloading ? 'Descargando…' : 'Descargar PDF'}</Button> : null}
            <Link to="/app/inicio"><Button icon={<Home size={17} />} type="button" variant="secondary">Volver al inicio</Button></Link>
          </div>
        </div>
      </div>
    </div>
  );
}

function ResultRow({ label, value }: { label: string; value: string }) {
  return <div className="grid gap-1 px-4 py-3 sm:grid-cols-[120px_1fr]"><dt className="text-muted">{label}</dt><dd className="font-semibold text-text">{value}</dd></div>;
}
