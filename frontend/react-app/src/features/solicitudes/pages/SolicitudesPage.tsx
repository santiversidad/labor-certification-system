import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { EmptyState } from '../../../components/ui/EmptyState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { SolicitudesTable } from '../components/SolicitudesTable';
import { solicitudesService } from '../services/solicitudes.service';
import type { SolicitudEstado, TipoCertificado } from '../types/solicitud.types';

export function SolicitudesPage() {
  const [estado, setEstado] = useState<SolicitudEstado | ''>('');
  const [tipo, setTipo] = useState<TipoCertificado | ''>('');
  const [fecha, setFecha] = useState('');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['solicitudes'],
    queryFn: solicitudesService.list,
  });

  const filteredSolicitudes = useMemo(() => {
    const solicitudes = data?.data ?? [];

    return solicitudes.filter((solicitud) => {
      const matchesEstado = estado ? solicitud.estado === estado : true;
      const matchesTipo = tipo ? solicitud.tipo_certificado === tipo : true;
      const matchesFecha = fecha ? (solicitud.created_at ?? '').startsWith(fecha) : true;
      return matchesEstado && matchesTipo && matchesFecha;
    });
  }, [data, estado, fecha, tipo]);

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Solicitudes para revision" description="Consulte y filtre las solicitudes radicadas antes de revisarlas." />
      <Card title="Filtros" description="Filtre por estado, tipo de certificado o fecha de solicitud.">
        <div className="grid gap-3 md:grid-cols-3">
          <label className="space-y-1 text-sm">
            <span className="font-medium text-text">Estado</span>
            <select className="min-h-10 w-full rounded-md border border-border bg-surface px-3" value={estado} onChange={(event) => setEstado(event.target.value as SolicitudEstado | '')}>
              <option value="">Todos</option>
              <option value="pendiente">Pendiente</option>
              <option value="en_revision">En revision</option>
              <option value="requiere_pago">Requiere pago</option>
              <option value="pago_pendiente">Pago pendiente</option>
              <option value="pago_validado">Pago validado</option>
              <option value="aprobado">Aprobado</option>
              <option value="rechazado">Rechazado</option>
              <option value="generado">Generado</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium text-text">Tipo</span>
            <select className="min-h-10 w-full rounded-md border border-border bg-surface px-3" value={tipo} onChange={(event) => setTipo(event.target.value as TipoCertificado | '')}>
              <option value="">Todos</option>
              <option value="laboral">Laboral</option>
              <option value="funciones">Funciones</option>
              <option value="salario">Salario</option>
              <option value="laboral_salario">Laboral con salario</option>
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium text-text">Fecha</span>
            <input className="min-h-10 w-full rounded-md border border-border bg-surface px-3" type="date" value={fecha} onChange={(event) => setFecha(event.target.value)} />
          </label>
        </div>
      </Card>
      <Card title="Solicitudes registradas" description="Listado operativo para revision de solicitudes.">
        {filteredSolicitudes.length ? <SolicitudesTable solicitudes={filteredSolicitudes} /> : <EmptyState title="Sin solicitudes" description="No hay solicitudes que coincidan con los filtros aplicados." />}
      </Card>
    </div>
  );
}
