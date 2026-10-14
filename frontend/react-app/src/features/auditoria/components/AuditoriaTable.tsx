import { Eye } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Modal } from '../../../components/ui/Modal';
import { Table, type TableColumn } from '../../../components/ui/Table';
import { formatDate } from '../../../lib/formatters/dates';
import type { AuditLog } from '../types/auditoria.types';
import { certificateTypeLabel } from '../../solicitudes/utils/certificateType';

const visibleMetadata: Record<string, string> = {
  tipo_certificado: 'Tipo de certificado',
  periodo_mes: 'Periodo',
  estado: 'Estado',
  estado_anterior: 'Estado anterior',
  estado_nuevo: 'Estado nuevo',
  resultado: 'Resultado',
  solicitud_id: 'Solicitud',
  snapshot_schema_version: 'Versión del registro',
  motivo: 'Motivo',
};

function metadataValue(key: string, value: unknown): string {
  if (key === 'tipo_certificado') return certificateTypeLabel(typeof value === 'string' ? value : null);
  return String(value);
}

function auditResult(log: AuditLog) {
  const explicit = log.metadata?.resultado;
  if (typeof explicit === 'string') return explicit;
  return /error|fall|rechaz/i.test(log.accion) ? 'Fallido' : 'Registrado';
}

export function AuditoriaTable({ logs, loading = false }: { logs: AuditLog[]; loading?: boolean }) {
  const [selected, setSelected] = useState<AuditLog | null>(null);
  const columns: TableColumn<AuditLog>[] = [
    { header: 'Fecha', accessor: (row) => formatDate(row.created_at) },
    { header: 'Usuario', accessor: (row) => row.user?.name ?? 'Sistema' },
    { header: 'Acción', accessor: (row) => <span className="font-medium text-text">{row.accion.replaceAll('_', ' ')}</span> },
    { header: 'Recurso', accessor: (row) => row.modelo || 'No informado' },
    { header: 'Resultado', accessor: (row) => { const result = auditResult(row); return <Badge tone={result === 'Fallido' ? 'red' : 'green'}>{result}</Badge>; } },
    { header: 'Detalle', accessor: (row) => <Button aria-label={`Ver detalle de ${row.accion}`} icon={<Eye size={15} />} onClick={() => setSelected(row)} type="button" variant="ghost" /> },
  ];
  const metadata = selected ? Object.entries(selected.metadata ?? {}).filter(([key, value]) => key in visibleMetadata && ['string', 'number', 'boolean'].includes(typeof value)) : [];
  return <>
    <Table caption="Eventos de auditoría" columns={columns} data={logs} emptyMessage="No hay eventos que coincidan con los filtros." loading={loading} />
    <Modal onClose={() => setSelected(null)} open={Boolean(selected)} title="Detalle del evento">
      {selected ? <dl className="grid gap-4 text-sm sm:grid-cols-2"><Detail label="Fecha" value={formatDate(selected.created_at)} /><Detail label="Usuario" value={selected.user?.name ?? 'Sistema'} /><Detail label="Acción" value={selected.accion.replaceAll('_', ' ')} /><Detail label="Recurso" value={`${selected.modelo}${selected.modelo_id ? ` #${selected.modelo_id}` : ''}`} /><Detail label="Resultado" value={auditResult(selected)} /><Detail label="Dirección IP" value={selected.ip_address ?? 'No disponible'} />{selected.descripcion ? <div className="sm:col-span-2"><Detail label="Descripción" value={selected.descripcion} /></div> : null}{metadata.length ? <div className="border-t border-border pt-4 sm:col-span-2"><p className="mb-3 font-semibold text-text">Información adicional</p><div className="grid gap-3 sm:grid-cols-2">{metadata.map(([key, value]) => <Detail key={key} label={visibleMetadata[key]} value={metadataValue(key, value)} />)}</div></div> : null}</dl> : null}
    </Modal>
  </>;
}

function Detail({ label, value }: { label: string; value: string }) {
  return <div><dt className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</dt><dd className="mt-1 font-medium text-text">{value}</dd></div>;
}
