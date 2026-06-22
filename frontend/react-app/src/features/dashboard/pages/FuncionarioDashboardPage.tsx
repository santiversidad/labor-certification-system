import { FileCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { useAuth } from '../../auth/hooks/useAuth';

export function FuncionarioDashboardPage() {
  const { user } = useAuth();

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Funcionario"
        title="Solicitud de certificacion laboral"
        description="Desde esta pantalla puede registrar una nueva solicitud. La Direccion de Personal revisara la informacion enviada."
      />

      <Card
        title={`Bienvenido${user?.name ? `, ${user.name}` : ''}`}
        description="Para iniciar el tramite, diligencie el formulario de solicitud de certificacion laboral."
        actions={(
          <Link to="/app/solicitudes/nueva">
            <Button icon={<FileCheck size={16} />} type="button">Solicitar certificado laboral</Button>
          </Link>
        )}
      >
        <p className="text-sm leading-6 text-muted">
          No se muestran estadisticas, historiales ni listados administrativos para este rol.
        </p>
      </Card>
    </div>
  );
}
