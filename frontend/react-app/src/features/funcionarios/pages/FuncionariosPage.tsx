import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { ConfirmDialog } from '../../../components/ui/ConfirmDialog';
import { Input } from '../../../components/ui/Input';
import { PageHeader } from '../../../components/ui/PageHeader';
import { Select } from '../../../components/ui/Select';
import { getErrorMessage } from '../../../lib/utils/errors';
import { cargosService } from '../../cargos/services/cargos.service';
import { FuncionariosTable } from '../components/FuncionariosTable';
import { funcionariosService } from '../services/funcionarios.service';
import type { Funcionario } from '../types/funcionario.types';

type ConfirmAction = { kind: 'reset'; id: number } | { kind: 'toggle'; row: Funcionario } | null;

export function FuncionariosPage() {
  const client = useQueryClient();
  const [page, setPage] = useState(1);
  const [q, setQ] = useState('');
  const [cargo, setCargo] = useState('');
  const [dependencia, setDependencia] = useState('');
  const [estado, setEstado] = useState('');
  const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
  const filters = { page, per_page: 15, q: q || undefined, cargo_id: cargo || undefined, dependencia: dependencia || undefined, estado: estado || undefined };
  const query = useQuery({ queryKey: ['funcionarios', filters], queryFn: () => funcionariosService.list(filters) });
  const cargos = useQuery({ queryKey: ['cargos-opciones'], queryFn: cargosService.options });
  const reset = useMutation({ mutationFn: funcionariosService.resetAccess, onSuccess: () => { setConfirmAction(null); client.invalidateQueries({ queryKey: ['funcionarios'] }); } });
  const toggle = useMutation({ mutationFn: (row: Funcionario) => funcionariosService.setEstado(row.id, row.estado === 'activo' ? 'suspendido' : 'activo'), onSuccess: () => { setConfirmAction(null); client.invalidateQueries({ queryKey: ['funcionarios'] }); } });
  const error = query.error ?? reset.error ?? toggle.error;
  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;

  function confirm() {
    if (confirmAction?.kind === 'reset') reset.mutate(confirmAction.id);
    if (confirmAction?.kind === 'toggle') toggle.mutate(confirmAction.row);
  }

  return (
    <div className="app-page">
      <PageHeader eyebrow="Talento Humano" title="Funcionarios" description="Administre vinculación, cargo, ficha normativa y acceso al autoservicio." actions={<Link to="/admin/funcionarios/nuevo"><Button icon={<Plus size={17} />} type="button">Crear funcionario</Button></Link>} />
      <section className="surface-section space-y-5 p-5 sm:p-6">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_180px]">
          <div className="relative"><Search aria-hidden="true" className="absolute left-3 top-[39px] z-10 text-muted" size={16} /><Input className="pl-9" label="Buscar" onChange={(event) => { setQ(event.target.value); setPage(1); }} placeholder="Cédula o nombre" value={q} /></div>
          <Select label="Cargo" onChange={(event) => { setCargo(event.target.value); setPage(1); }} value={cargo}><option value="">Todos los cargos</option>{cargos.data?.data.map((item) => <option key={item.id} value={item.id}>{item.denominacion}</option>)}</Select>
          <Input label="Dependencia" onChange={(event) => { setDependencia(event.target.value); setPage(1); }} placeholder="Todas" value={dependencia} />
          <Select label="Estado" onChange={(event) => { setEstado(event.target.value); setPage(1); }} value={estado}><option value="">Todos</option><option value="activo">Activo</option><option value="retirado">Retirado</option><option value="suspendido">Suspendido</option></Select>
        </div>
        {error && !query.isLoading ? <ErrorState message={getErrorMessage(error)} /> : null}
        {reset.isSuccess ? <Alert title="Acceso restablecido" tone="success">El próximo ingreso exigirá cambio de contraseña y las sesiones anteriores fueron revocadas.</Alert> : null}
        <FuncionariosTable
          funcionarios={rows}
          loading={query.isLoading}
          onReset={(id) => setConfirmAction({ kind: 'reset', id })}
          onToggle={(row) => setConfirmAction({ kind: 'toggle', row })}
          pagination={{ currentPage: page, lastPage: meta?.last_page ?? 1, total: meta?.total ?? rows.length, onPageChange: setPage }}
        />
      </section>
      <ConfirmDialog
        confirmLabel={confirmAction?.kind === 'reset' ? 'Restablecer acceso' : confirmAction?.kind === 'toggle' && confirmAction.row.estado === 'activo' ? 'Desactivar' : 'Activar'}
        confirmVariant={confirmAction?.kind === 'toggle' && confirmAction.row.estado === 'activo' ? 'danger' : 'primary'}
        description={confirmAction?.kind === 'reset' ? 'Se revocarán las sesiones activas y el próximo ingreso exigirá una nueva contraseña.' : 'Este cambio afecta inmediatamente el acceso del funcionario al autoservicio.'}
        disabled={reset.isPending || toggle.isPending}
        onCancel={() => setConfirmAction(null)}
        onConfirm={confirm}
        open={Boolean(confirmAction)}
        title={confirmAction?.kind === 'reset' ? 'Restablecer acceso' : 'Cambiar estado del funcionario'}
      />
    </div>
  );
}
