import { Download, FileCheck2, FilePlus2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';

export function FuncionarioCertificateActions() {
  return (
    <Card title="Gestion de certificados" description="Accesos directos a las acciones mas frecuentes del tramite.">
      <div className="grid gap-3 sm:grid-cols-3">
        <Link className="rounded-md border border-border bg-background p-4 transition hover:border-govBlue hover:bg-blue-50" to="/app/solicitudes/nueva">
          <FilePlus2 className="text-govBlue" size={22} />
          <p className="mt-3 text-sm font-semibold text-text">Nueva solicitud</p>
          <p className="mt-1 text-xs text-muted">Radique una certificacion laboral.</p>
        </Link>
        <Link className="rounded-md border border-border bg-background p-4 transition hover:border-govBlue hover:bg-blue-50" to="/app/solicitudes">
          <FileCheck2 className="text-govBlue" size={22} />
          <p className="mt-3 text-sm font-semibold text-text">Seguimiento</p>
          <p className="mt-1 text-xs text-muted">Revise el avance de sus solicitudes.</p>
        </Link>
        <Link className="rounded-md border border-border bg-background p-4 transition hover:border-govBlue hover:bg-blue-50" to="/app/certificados">
          <Download className="text-govBlue" size={22} />
          <p className="mt-3 text-sm font-semibold text-text">Descargas</p>
          <p className="mt-1 text-xs text-muted">Consulte certificados disponibles.</p>
        </Link>
      </div>
      <div className="mt-4">
        <Link to="/app/solicitudes/nueva">
          <Button type="button">Enviar nueva solicitud</Button>
        </Link>
      </div>
    </Card>
  );
}
