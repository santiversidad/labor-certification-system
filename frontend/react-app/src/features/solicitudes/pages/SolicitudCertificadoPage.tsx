import { PageHeader } from '../../../components/ui/PageHeader';
import { SolicitudForm } from '../components/SolicitudForm';

export function SolicitudCertificadoPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Nueva solicitud"
        title="Solicitar certificacion laboral"
        description="Complete la informacion del tramite. La solicitud sera registrada en estado pendiente y quedara disponible para revision por la Direccion de Personal."
      />
      <SolicitudForm />
    </div>
  );
}
