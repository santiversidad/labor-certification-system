import { Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudCertificacion } from '../../solicitudes/types/solicitud.types';

export function FuncionarioRecentRequests({ solicitudes }: { solicitudes: SolicitudCertificacion[] }) {
  return (
    <Card
      actions={(
        <Link to="/app/solicitudes">
          <Button type="button" variant="secondary">Ver todas</Button>
        </Link>
      )}
      title="Solicitudes recientes"
      description="Estado actualizado desde el servicio de solicitudes."
    >
      <div className="divide-y divide-border">
        {solicitudes.map((solicitud) => (
          <Link className="grid gap-2 py-4 transition hover:bg-background sm:grid-cols-[1fr_auto]" key={solicitud.id} to={`/app/solicitudes/${solicitud.id}`}>
            <div>
              <p className="text-sm font-semibold text-text">{solicitud.radicado}</p>
              <p className="mt-1 text-xs text-muted">Certificado {solicitud.tipo} - solicitado el {solicitud.fechaSolicitud}</p>
            </div>
            <SolicitudStatusBadge estado={solicitud.estado} />
          </Link>
        ))}
      </div>
    </Card>
  );
}
