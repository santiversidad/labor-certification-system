import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Alert } from '../../../components/ui/Alert';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Table } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import { SolicitudStatusBadge } from '../../solicitudes/components/SolicitudStatusBadge';
import type { SolicitudPago } from '../../solicitudes/types/solicitud.types';
import { certificateTypeLabel } from '../../solicitudes/utils/certificateType';
import { useAuth } from '../../auth/hooks/useAuth';
import { paymentWorkflowService } from '../services/paymentWorkflow.service';

export function PaymentReviewPage() {
  const { user } = useAuth();
  const permitted = Boolean(user?.roles.includes('secretario') && user.permisos.includes('pagos.ver'));
  const [estado, setEstado] = useState('pago_en_revision');
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ['secretaria-pagos', estado, page], queryFn: () => paymentWorkflowService.list(estado, page), enabled: permitted });
  if (!permitted) return <Alert title="Acceso restringido" variant="error">No tiene permiso para consultar pagos.</Alert>;
  if (query.isError) return <ErrorState />;
  return <div className="app-page">
    <PageHeader eyebrow="Secretaría" title="Validación de pagos" description="Revise la evidencia del pago. Al confirmarlo, el sistema expide la certificación automáticamente." />
    <label className="block max-w-sm space-y-2 text-sm font-semibold">Estado del pago<select className="field-control" onChange={(e) => { setEstado(e.target.value); setPage(1); }} value={estado}>
      <option value="pago_en_revision">En revisión</option><option value="pago_rechazado">Rechazados</option><option value="pago_confirmado">Confirmados</option><option value="generada">Generados</option><option value="expedicion_fallida">Error de expedición</option><option value="pendiente_pago">Pendientes de comprobante</option>
    </select></label>
    <Table<SolicitudPago> data={query.data?.data ?? []} loading={query.isLoading} columns={[
      { header: 'Radicado', accessor: (s) => <Link className="font-semibold text-primary underline" to={`/admin/pagos/${s.id}`}>{s.radicado}</Link> },
      { header: 'Funcionario', accessor: (s) => <span>{s.funcionario.nombres} {s.funcionario.apellidos}<span className="block text-xs text-muted">{s.funcionario.documento_parcial}</span></span> },
      { header: 'Tipo', accessor: (s) => certificateTypeLabel(s.tipo_certificado) },
      { header: 'Solicitud', accessor: (s) => s.created_at ? formatDate(s.created_at) : '—' },
      { header: 'Comprobante', accessor: (s) => s.comprobantes.at(-1)?.created_at ? formatDate(s.comprobantes.at(-1)!.created_at!) : '—' },
      { header: 'Estado', accessor: (s) => <SolicitudStatusBadge estado={s.estado} /> },
    ]} pagination={query.data?.meta ? { currentPage: page, lastPage: query.data.meta.last_page, total: query.data.meta.total, onPageChange: setPage } : undefined} />
  </div>;
}
