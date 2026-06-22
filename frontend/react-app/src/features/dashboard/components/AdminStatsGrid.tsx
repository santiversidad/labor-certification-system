import { BadgeCheck, CheckCircle, ClipboardList, Clock3, CreditCard, Users, XCircle } from 'lucide-react';
import { Card } from '../../../components/ui/Card';

type AdminStat = {
  label: string;
  value: number;
  helper: string;
  icon: typeof Clock3;
};

export function AdminStatsGrid({
  solicitudesPendientes,
  solicitudesTotales,
  solicitudesAprobadas,
  solicitudesRechazadas,
  certificadosGenerados,
  pagosPorValidar,
  funcionariosRegistrados,
}: {
  solicitudesPendientes: number;
  solicitudesTotales: number;
  solicitudesAprobadas: number;
  solicitudesRechazadas: number;
  certificadosGenerados: number;
  pagosPorValidar: number;
  funcionariosRegistrados: number;
}) {
  const stats: AdminStat[] = [
    { label: 'Funcionarios registrados', value: funcionariosRegistrados, helper: 'Base institucional', icon: Users },
    { label: 'Solicitudes totales', value: solicitudesTotales, helper: 'Tramites radicados', icon: ClipboardList },
    { label: 'Solicitudes pendientes', value: solicitudesPendientes, helper: 'Requieren revision', icon: Clock3 },
    { label: 'Solicitudes aprobadas', value: solicitudesAprobadas, helper: 'Aprobadas o certificadas', icon: CheckCircle },
    { label: 'Solicitudes rechazadas', value: solicitudesRechazadas, helper: 'No continuan proceso', icon: XCircle },
    { label: 'Certificados generados', value: certificadosGenerados, helper: 'Disponibles para descarga', icon: BadgeCheck },
    { label: 'Pagos por validar', value: pagosPorValidar, helper: 'Soportes cargados', icon: CreditCard },
  ];

  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {stats.map((stat) => (
        <Card className="p-4" key={stat.label}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm text-muted">{stat.label}</p>
              <p className="mt-2 text-3xl font-semibold text-text">{stat.value}</p>
              <p className="mt-1 text-xs text-muted">{stat.helper}</p>
            </div>
            <span className="rounded-md bg-blue-50 p-2 text-govBlue">
              <stat.icon size={20} />
            </span>
          </div>
        </Card>
      ))}
    </div>
  );
}
