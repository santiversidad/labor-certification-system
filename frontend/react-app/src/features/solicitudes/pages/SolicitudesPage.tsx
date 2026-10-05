import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Table } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../components/SolicitudStatusBadge';
import { solicitudesService } from '../services/solicitudes.service';
import type { SolicitudCertificacion } from '../types/solicitud.types';
import { certificateTypeLabel } from '../utils/certificateType';

export function SolicitudesPage() {
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ['solicitudes', page], queryFn: () => solicitudesService.listPage(page) });
  if (query.isError) return <ErrorState />;
  return <div className="app-page"><PageHeader appearance="institutional" title="Mis solicitudes" description="Consulte su pago, los comprobantes y la expedición de cada certificado." />
    <Table<SolicitudCertificacion> loading={query.isLoading} data={query.data?.data ?? []} columns={[
      { header: 'Radicado', accessor: (s) => <Link className="font-semibold text-primary underline" to={`/app/solicitudes/${s.id}`}>{s.radicado}</Link> },
      { header: 'Tipo', accessor: (s) => certificateTypeLabel(s.tipo_certificado) },
      { header: 'Estado', accessor: (s) => <SolicitudStatusBadge estado={s.estado} /> },
      { header: 'Fecha', accessor: (s) => s.created_at ? formatDate(s.created_at) : '—' },
    ]} pagination={query.data?.meta ? { currentPage: page, lastPage: query.data.meta.last_page, total: query.data.meta.total, onPageChange: setPage } : undefined} />
  </div>;
}
