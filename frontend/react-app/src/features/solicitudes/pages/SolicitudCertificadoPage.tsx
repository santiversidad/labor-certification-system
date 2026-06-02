import { SolicitudForm } from '../components/SolicitudForm';

export function SolicitudCertificadoPage() {
  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-govBlue/15 bg-surface p-6">
        <p className="text-sm font-semibold text-govBlue">Solicitud de Certificado V2</p>
        <h1 className="mt-2 text-2xl font-semibold text-text">Solicitar certificacion laboral</h1>
        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted">
          Complete la informacion del tramite. La solicitud sera registrada en estado pendiente y quedara disponible para revision por la dependencia correspondiente.
        </p>
      </div>
      <SolicitudForm />
    </div>
  );
}
