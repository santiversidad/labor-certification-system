import { CheckCircle, Download, Home, Hourglass } from 'lucide-react';
import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { getErrorMessage } from '../../../lib/utils/errors';
import { solicitudesService } from '../services/solicitudes.service';
import type { ExpedicionCertificacion } from '../types/solicitud.types';

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
    <div className="mx-auto max-w-2xl rounded-xl border border-border bg-white p-7 sm:p-10">
      <div className="flex gap-4">
        {generada ? <CheckCircle className="shrink-0 text-villavoGreen" size={36} /> : <Hourglass className="shrink-0 text-amber-600" size={36} />}
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Radicado {resultado.solicitud.radicado}</p>
          <h1 className="mt-2 text-2xl font-semibold text-text">{generada ? 'Certificación generada correctamente' : 'Pendiente de confirmación de pago'}</h1>
          <p className="mt-3 text-sm leading-6 text-muted">{generada ? 'El documento está listo. Descárguelo ahora; el enlace inmediato tiene vigencia limitada.' : 'La certificación se generará automáticamente cuando una futura pasarela confirme el pago al backend.'}</p>
          {resultado.orden_pago ? <p className="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-text">Referencia de pago: <strong>{resultado.orden_pago.referencia}</strong></p> : null}
          {error ? <p className="mt-4 text-sm text-villavoRed">{error}</p> : null}
          <div className="mt-7 flex flex-wrap gap-3">
            {generada ? <Button disabled={downloading} icon={<Download size={17} />} onClick={descargar} type="button">{downloading ? 'Descargando…' : 'Descargar PDF'}</Button> : null}
            <Link to="/app/inicio"><Button icon={<Home size={17} />} type="button" variant="secondary">Volver al inicio</Button></Link>
          </div>
        </div>
      </div>
    </div>
  );
}
