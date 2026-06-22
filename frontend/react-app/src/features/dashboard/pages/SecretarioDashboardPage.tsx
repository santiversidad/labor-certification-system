import { useQuery } from '@tanstack/react-query';
import { BadgeCheck, ClipboardList, CreditCard } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { PageHeader } from '../../../components/ui/PageHeader';
import { certificadosService } from '../../certificados/services/certificados.service';
import { pagosService } from '../../pagos/services/pagos.service';
import { solicitudesService } from '../../solicitudes/services/solicitudes.service';
import { DashboardCard } from '../components/DashboardCard';

export function SecretarioDashboardPage() {
  const solicitudesQuery = useQuery({ queryKey: ['secretario-solicitudes-summary'], queryFn: solicitudesService.list });
  const pagosQuery = useQuery({ queryKey: ['secretario-pagos-summary'], queryFn: pagosService.list });
  const certificadosQuery = useQuery({ queryKey: ['secretario-certificados-summary'], queryFn: certificadosService.list });

  if (solicitudesQuery.isLoading || pagosQuery.isLoading || certificadosQuery.isLoading) {
    return <LoadingState />;
  }

  if (solicitudesQuery.isError || pagosQuery.isError || certificadosQuery.isError || !solicitudesQuery.data || !pagosQuery.data || !certificadosQuery.data) {
    return <ErrorState />;
  }

  const solicitudes = solicitudesQuery.data.data.data;
  const pagos = pagosQuery.data.data.data;
  const certificados = certificadosQuery.data.data.data;
  const pendientes = solicitudes.filter((item) => item.estado === 'pendiente' || item.estado === 'en_revision').length;
  const aprobadas = solicitudes.filter((item) => item.estado === 'aprobada' || item.estado === 'certificado_generado').length;
  const rechazadas = solicitudes.filter((item) => item.estado === 'rechazada').length;
  const pagosPendientes = pagos.filter((item) => item.estado === 'pendiente' || item.estado === 'cargado').length;

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Secretario"
        title="Gestion y revision de certificaciones"
        description="Revise solicitudes, valide soportes de pago y consulte certificados generados."
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <DashboardCard metric={{ label: 'Pendientes de revision', value: pendientes, tone: 'gold' }} />
        <DashboardCard metric={{ label: 'Aprobadas', value: aprobadas, tone: 'green' }} />
        <DashboardCard metric={{ label: 'Rechazadas', value: rechazadas, tone: 'red' }} />
        <DashboardCard metric={{ label: 'Pagos por validar', value: pagosPendientes, tone: 'blue' }} />
        <DashboardCard metric={{ label: 'Certificados generados', value: certificados.length, tone: 'green' }} />
      </div>

      <Card title="Accesos rapidos" description="Tareas operativas del proceso de certificacion laboral.">
        <div className="flex flex-col gap-3 sm:flex-row">
          <Link to="/app/solicitudes"><Button icon={<ClipboardList size={16} />} type="button">Revisar solicitudes</Button></Link>
          <Link to="/app/pagos"><Button icon={<CreditCard size={16} />} type="button" variant="secondary">Validar pagos</Button></Link>
          <Link to="/app/certificados"><Button icon={<BadgeCheck size={16} />} type="button" variant="secondary">Consultar certificados</Button></Link>
        </div>
      </Card>
    </div>
  );
}
