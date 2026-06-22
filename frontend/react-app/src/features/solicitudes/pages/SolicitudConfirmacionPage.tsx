import { CheckCircle, FilePlus, Home } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { PageHeader } from '../../../components/ui/PageHeader';

type ConfirmationState = {
  radicado?: string;
};

export function SolicitudConfirmacionPage() {
  const location = useLocation();
  const state = location.state as ConfirmationState | null;
  const radicado = state?.radicado ?? sessionStorage.getItem('ultima_solicitud_radicado');

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Solicitud registrada"
        title="Su solicitud fue registrada correctamente"
        description="La Direccion de Personal revisara la informacion y continuara el tramite conforme al proceso interno."
      />

      <Card>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
          <CheckCircle className="mt-1 text-villavoGreen" size={32} />
          <div className="space-y-3">
            <div>
              <p className="text-sm text-muted">Radicado</p>
              <p className="text-lg font-semibold text-text">{radicado ?? 'Pendiente de confirmacion del backend'}</p>
            </div>
            <p className="text-sm leading-6 text-muted">
              Guarde el numero de radicado para futuras consultas institucionales. La revision sera realizada por la dependencia correspondiente.
            </p>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Link to="/app/inicio">
                <Button icon={<Home size={16} />} type="button" variant="secondary">Volver al inicio</Button>
              </Link>
              <Link to="/app/solicitudes/nueva">
                <Button icon={<FilePlus size={16} />} type="button">Crear otra solicitud</Button>
              </Link>
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
}
