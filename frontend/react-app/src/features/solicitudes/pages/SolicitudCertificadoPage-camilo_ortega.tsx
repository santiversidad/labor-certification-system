import { PageHeader } from '../../../components/ui/PageHeader';
import { SolicitudForm } from '../components/SolicitudForm';

export function SolicitudCertificadoPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Nueva solicitud"
        title="Solicitar certificacion laboral"
        description="Seleccione si necesita la certificación con salario o sin salario y radique la solicitud."
      />
      <SolicitudForm />
    </div>
  );
}
