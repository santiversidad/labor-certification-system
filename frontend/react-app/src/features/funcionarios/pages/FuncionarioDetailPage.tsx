import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { KeyRound, Pencil, Power } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Alert } from '../../../components/ui/Alert';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { PageHeader } from '../../../components/ui/PageHeader';
import { getErrorMessage } from '../../../lib/utils/errors';
import { funcionariosService } from '../services/funcionarios.service';

export function FuncionarioDetailPage() {
  const { id = '' } = useParams();
  const client = useQueryClient();
  const [action, setAction] = useState<'toggle' | 'reset' | null>(null);
  const query = useQuery({ queryKey: ['funcionario', id], queryFn: () => funcionariosService.getById(id), enabled: Boolean(id) });
  const reset = useMutation({ mutationFn: () => funcionariosService.resetAccess(id), onSuccess: () => { setAction(null); client.invalidateQueries({ queryKey: ['funcionario', id] }); } });
  const toggle = useMutation({ mutationFn: () => funcionariosService.setEstado(Number(id), query.data?.data.estado === 'activo' ? 'suspendido' : 'activo'), onSuccess: () => { setAction(null); client.invalidateQueries({ queryKey: ['funcionario', id] }); } });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState />;
  const funcionario = query.data.data;
  const mutationError = reset.error ?? toggle.error;

  return (
    <div className="app-page max-w-6xl">
      <PageHeader eyebrow="Talento Humano" title={`${funcionario.nombres} ${funcionario.apellidos}`} description="Información institucional, vinculación, ficha normativa y estado de acceso." actions={<div className="flex flex-wrap gap-2"><Link to={`/admin/funcionarios/${id}/editar`}><Button icon={<Pencil size={16} />} type="button" variant="secondary">Editar</Button></Link><Button icon={<Power size={16} />} onClick={() => setAction('toggle')} type="button" variant={funcionario.estado === 'activo' ? 'danger' : 'success'}>{funcionario.estado === 'activo' ? 'Desactivar' : 'Activar'}</Button><Button icon={<KeyRound size={16} />} onClick={() => setAction('reset')} type="button" variant="secondary">Restablecer acceso</Button></div>} />
      {mutationError ? <ErrorState message={getErrorMessage(mutationError)} /> : null}
      {reset.isSuccess ? <Alert title="Acceso restablecido" tone="success">El funcionario deberá cambiar su contraseña temporal en el próximo ingreso.</Alert> : null}
      <div className="grid gap-6 lg:grid-cols-2">
        <DetailSection title="Información básica"><Detail label="Documento" value={`${funcionario.tipo_documento} ${funcionario.numero_documento}`} /><Detail label="Correo" value={funcionario.correo_institucional ?? 'Sin correo'} /><Detail label="Teléfono" value={funcionario.telefono ?? 'No informado'} /><Detail label="Estado" value={<Badge tone={funcionario.estado === 'activo' ? 'green' : 'gray'}>{funcionario.estado}</Badge>} /></DetailSection>
        <DetailSection title="Acceso"><Detail label="Cuenta" value={funcionario.usuario?.estado ? 'Activa' : 'Inactiva'} /><Detail label="Cambio de contraseña" value={funcionario.usuario?.must_change_password ? 'Pendiente' : 'Completado'} /><Detail label="Credencial" value="Administrada de forma segura; la contraseña nunca se muestra." /></DetailSection>
        <DetailSection title="Vinculación actual"><Detail label="Dependencia" value={funcionario.dependencia ?? 'No informada'} /><Detail label="Ingreso" value={funcionario.fecha_ingreso ?? 'No informado'} /><Detail label="Retiro" value={funcionario.fecha_retiro ?? 'No aplica'} /><Detail label="Tipo" value={funcionario.asignacion_actual?.tipo_vinculacion ?? 'No informado'} /></DetailSection>
        <DetailSection title="Cargo"><Detail label="Denominación" value={funcionario.cargo?.denominacion ?? 'Sin cargo'} /><Detail label="Código / grado" value={funcionario.cargo ? `${funcionario.cargo.codigo} / ${funcionario.cargo.grado}` : 'No informado'} /><Detail label="Naturaleza" value={funcionario.asignacion_actual?.naturaleza_cargo ?? 'No informada'} /></DetailSection>
        <div className="lg:col-span-2"><DetailSection title="Ficha del Manual"><Detail label="Source ID" value={funcionario.asignacion_actual?.ficha_manual?.source_id ?? funcionario.asignacion_actual?.manual_cargo_version_id ?? 'Sin ficha vigente'} /><Detail label="Área funcional" value={funcionario.asignacion_actual?.ficha_manual?.area_funcional ?? 'No informada'} /><Detail label="Versión" value={funcionario.asignacion_actual?.ficha_manual?.version ?? 'No informada'} /></DetailSection></div>
      </div>
      <ConfirmDialog confirmLabel={action === 'reset' ? 'Restablecer acceso' : funcionario.estado === 'activo' ? 'Desactivar' : 'Activar'} confirmVariant={action === 'toggle' && funcionario.estado === 'activo' ? 'danger' : 'primary'} description={action === 'reset' ? 'Se revocarán sesiones activas y se exigirá cambio de contraseña.' : 'El cambio afectará inmediatamente el acceso al autoservicio.'} disabled={reset.isPending || toggle.isPending} onCancel={() => setAction(null)} onConfirm={() => action === 'reset' ? reset.mutate() : toggle.mutate()} open={Boolean(action)} title={action === 'reset' ? 'Restablecer acceso' : 'Cambiar estado'} />
    </div>
  );
}

function DetailSection({ title, children }: { title: string; children: ReactNode }) {
  return <section className="surface-section h-full p-5 sm:p-6"><h2 className="border-b border-border pb-3 text-base font-bold text-text">{title}</h2><dl className="mt-4 grid gap-4 sm:grid-cols-2">{children}</dl></section>;
}

function Detail({ label, value }: { label: string; value: ReactNode }) {
  return <div><dt className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</dt><dd className="mt-1 text-sm font-medium text-text">{value}</dd></div>;
}
