import { PageHeader } from '../../../components/ui/PageHeader';
import { SolicitudForm } from '../components/SolicitudForm';

export function SolicitudCertificadoPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Autoservicio"
        title="Confirmar certificación laboral"
        description="Revise el tipo de certificado. No existe un paso de aprobación humana."
      />
      <SolicitudForm />
    </div>
  );
}
