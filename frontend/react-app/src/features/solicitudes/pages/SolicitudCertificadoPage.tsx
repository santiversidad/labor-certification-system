import { PageHeader } from '../../../components/ui/PageHeader';
import { Navigate, useParams } from 'react-router-dom';
import { SolicitudForm } from '../components/SolicitudForm';
import { CERTIFICATE_TYPE_LABELS } from '../utils/certificateType';

export function SolicitudCertificadoPage() {
  const { tipo } = useParams();
  if (tipo !== 'sencillo' && tipo !== 'funciones') return <Navigate replace to="/app/inicio" />;
  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Autoservicio"
        title="Solicitud de certificado"
        description={`Revise los datos antes de generar su ${CERTIFICATE_TYPE_LABELS[tipo].toLowerCase()}.`}
      />
      <SolicitudForm tipo={tipo} />
    </div>
  );
}
