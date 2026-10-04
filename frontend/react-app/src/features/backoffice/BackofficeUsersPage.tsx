import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Alert } from '../../components/ui/Alert';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { Table } from '../../components/ui/Table';
import type { ApiResponse } from '../../lib/api/api.types';
import { apiClient } from '../../lib/api/apiClient';
import { getErrorMessage, getValidationErrors } from '../../lib/utils/errors';
import type { User } from '../../types/common.types';

const endpoint = '/admin/usuarios-internos';
type Action = 'create' | 'edit' | 'deactivate' | 'reactivate' | 'reset' | 'role' | 'mfa';
type Selection = { action: Action; user?: User };
type Result = { success: boolean; data: { user: User; temporary_password: string | null } };
const roleLabel = (user: User) => user.roles.includes('admin') ? 'Administrador' : 'Secretaría';
const dateLabel = (value?: string | null) => value ? new Date(value).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Bogota' }) : 'Sin acceso registrado';
const actionTitles: Record<Action, string> = {
  create: 'Crear usuario interno', edit: 'Editar usuario interno', deactivate: 'Desactivar acceso',
  reactivate: 'Reactivar acceso', reset: 'Restablecer contraseña', role: 'Cambiar rol',
  mfa: 'Restablecer MFA',
};

export function BackofficeUsersPage() {
  const client = useQueryClient();
  const [q, setQ] = useState('');
  const [role, setRole] = useState('');
  const [estado, setEstado] = useState('');
  const [page, setPage] = useState(1);
  const [selection, setSelection] = useState<Selection | null>(null);
  // The secret only lives in this modal state. No mutation cache, browser storage or URL.
  const [secret, setSecret] = useState<string | null>(null);
  const [notice, setNotice] = useState('');
  const query = useQuery({
    queryKey: ['backoffice-users', q, role, estado, page],
    queryFn: async () => (await apiClient.get<ApiResponse<User[]>>(endpoint, {
      params: { q: q || undefined, role: role || undefined, estado: estado || undefined, page },
    })).data,
  });
  const select = (action: Action, user?: User) => { setNotice(''); setSelection({ action, user }); };
  return (
    <div className="min-w-0 space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div><p className="eyebrow">Administración de accesos</p><h1 className="mt-2 text-2xl font-bold text-text">Usuarios internos</h1><p className="mt-2 max-w-2xl text-sm text-muted">Cada persona utiliza una cuenta individual. Para solicitar sus certificados debe usar una cuenta de funcionario separada.</p></div>
        <Button onClick={() => select('create')}>Crear usuario interno</Button>
      </div>
      {notice ? <Alert announce variant="success">{notice}</Alert> : null}
      <div className="grid gap-4 sm:grid-cols-3">
        <Input label="Buscar por nombre o usuario" onChange={(event) => { setQ(event.target.value); setPage(1); }} value={q} />
        <label className="space-y-1.5 text-sm font-semibold">Rol<select className="field-control" onChange={(event) => { setRole(event.target.value); setPage(1); }} value={role}><option value="">Todos los roles</option><option value="admin">Administrador</option><option value="secretario">Secretaría</option></select></label>
        <label className="space-y-1.5 text-sm font-semibold">Estado<select className="field-control" onChange={(event) => { setEstado(event.target.value); setPage(1); }} value={estado}><option value="">Todos los estados</option><option value="1">Activo</option><option value="0">Inactivo</option></select></label>
      </div>
      {query.isError ? <Alert variant="error">{getErrorMessage(query.error)}</Alert> : null}
      <Table caption="Cuentas internas individuales" data={query.data?.data ?? []} loading={query.isPending}
        columns={[
          { header: 'Nombre', accessor: 'name' }, { header: 'Usuario', accessor: 'username' },
          { header: 'Rol', accessor: roleLabel },
          { header: 'Estado', accessor: (user) => <Badge tone={user.estado ? 'green' : 'red'}>{user.estado ? 'Activo' : 'Inactivo'}</Badge> },
          { header: 'Último acceso', accessor: (user) => dateLabel(user.last_login_at) },
          { header: 'Fecha creación', accessor: (user) => dateLabel(user.created_at) },
          { header: 'Acciones', accessor: (user) => <div className="flex w-60 flex-wrap gap-2">
            <Button variant="secondary" onClick={() => select('edit', user)}>Editar</Button>
            <Button variant="secondary" onClick={() => select('role', user)}>Cambiar rol</Button>
            <Button variant="secondary" onClick={() => select(user.estado ? 'deactivate' : 'reactivate', user)}>{user.estado ? 'Desactivar acceso' : 'Reactivar acceso'}</Button>
            <Button variant="secondary" onClick={() => select('reset', user)}>Restablecer contraseña</Button>
            <Button variant="secondary" onClick={() => select('mfa', user)}>Restablecer MFA</Button>
          </div> },
        ]}
        pagination={query.data?.meta ? { currentPage: query.data.meta.current_page, lastPage: query.data.meta.last_page, total: query.data.meta.total, onPageChange: setPage } : undefined} />
      {selection ? <AccountAction key={selection.action + '-' + (selection.user?.id ?? 'new')} selection={selection} onClose={() => setSelection(null)} onSuccess={(password) => {
        setSelection(null); setSecret(password); setNotice('Acceso actualizado correctamente.');
        void client.invalidateQueries({ queryKey: ['backoffice-users'] });
      }} /> : null}
      {secret !== null ? <TemporaryPasswordModal password={secret} onClose={() => setSecret(null)} /> : null}
    </div>
  );
}

function TemporaryPasswordModal({ password, onClose }: { password: string; onClose: () => void }) {
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState(false);
  return <Modal open title="Contraseña temporal" onClose={onClose}>
    <div className="space-y-4">
      <Alert variant="warning">Guarde esta contraseña temporal. No volverá a mostrarse. Comparta esta contraseña por un canal institucional seguro.</Alert>
      <Input autoComplete="off" data-testid="temporary-password" label="Contraseña temporal generada" readOnly value={password} />
      <p className="text-sm text-muted">El usuario deberá cambiarla en su primer ingreso.</p>
      {error ? <Alert variant="error">No se pudo copiar. Seleccione la contraseña y cópiela con el teclado.</Alert> : null}
      <div className="flex flex-wrap gap-3">
        <Button onClick={() => {
          if (!navigator.clipboard) { setError(true); return; }
          void navigator.clipboard.writeText(password).then(() => setCopied(true)).catch(() => setError(true));
        }}>Copiar contraseña</Button>
        <Button variant="secondary" onClick={onClose}>Cerrar y descartar</Button>
      </div>
      {copied ? <p role="status">Contraseña copiada.</p> : null}
    </div>
  </Modal>;
}

function AccountAction({ selection, onClose, onSuccess }: { selection: Selection; onClose: () => void; onSuccess: (password: string | null) => void }) {
  const { action, user } = selection;
  const [name, setName] = useState(user?.name ?? '');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState(user?.email ?? '');
  const [role, setRole] = useState<string>(user?.roles[0] ?? 'secretario');
  const [motivo, setMotivo] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState('');
  const submit = async () => {
    setPending(true); setError('');
    try {
      let password: string | null = null;
      if (action === 'create') {
        password = (await apiClient.post<Result>(endpoint, { name, username, email: email || null, role })).data.data.temporary_password;
      } else if (action === 'edit') {
        await apiClient.patch(endpoint + '/' + user!.id, { name, email: email || null });
      } else {
        const path = { deactivate: 'desactivar', reactivate: 'reactivar', reset: 'reset-password', role: 'cambiar-rol', mfa: 'reset-mfa' }[action];
        const payload = action === 'deactivate' ? { motivo } : action === 'role' ? { role } : {};
        const result = await apiClient.post<Result>(endpoint + '/' + user!.id + '/' + path, payload);
        password = result.data.data?.temporary_password ?? null;
      }
      onSuccess(password);
    } catch (failure) {
      const messages = Object.values(getValidationErrors(failure)).flat();
      setError(messages.length ? messages.join(' ') : getErrorMessage(failure));
    } finally { setPending(false); }
  };
  return <Modal open title={actionTitles[action]} onClose={() => { if (!pending) onClose(); }}>
    <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); void submit(); }}>
      {user ? <p className="text-sm font-semibold">{user.name} · {user.username}</p> : null}
      {action === 'create' || action === 'edit' ? <>
        <Input label="Nombre descriptivo" maxLength={150} required value={name} onChange={(event) => setName(event.target.value)} />
        {action === 'create' ? <><Input autoComplete="off" label="Usuario institucional individual" minLength={3} maxLength={64} required value={username} onChange={(event) => setUsername(event.target.value)} /><p className="text-xs text-muted">3–64 caracteres: letras, números, punto, guion o guion bajo. No use solo números. El usuario será permanente.</p></> : null}
        <Input label="Correo institucional (opcional)" maxLength={150} type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
      </> : null}
      {action === 'create' || action === 'role' ? <label className="block space-y-1.5 text-sm font-semibold">Rol de acceso<select className="field-control" value={role} onChange={(event) => setRole(event.target.value)}><option value="admin">Administrador</option><option value="secretario">Secretaría</option></select></label> : null}
      {action === 'deactivate' ? <><Alert variant="warning">Esta acción cerrará las sesiones activas de este usuario y le impedirá ingresar.</Alert><label className="block space-y-1.5 text-sm font-semibold">Motivo de desactivación<textarea className="field-control" maxLength={1000} minLength={3} required value={motivo} onChange={(event) => setMotivo(event.target.value)} /></label></> : null}
      {action === 'reset' || action === 'reactivate' ? <Alert variant="warning">Se generará una nueva contraseña temporal y se cerrarán las sesiones anteriores. El usuario deberá ingresar y cambiarla.</Alert> : null}
      {action === 'role' ? <Alert variant="warning">El cambio de rol cerrará las sesiones activas. El usuario deberá ingresar nuevamente.</Alert> : null}
      {action === 'mfa' ? <Alert variant="warning">Verifique la identidad de esta persona por el procedimiento institucional. Se revocarán sus sesiones y deberá configurar un nuevo autenticador. Otro administrador debe realizar esta acción.</Alert> : null}
      {error ? <Alert variant="error">{error}</Alert> : null}
      <div className="flex flex-wrap gap-3">
        <Button disabled={pending} type="submit">{pending ? 'Guardando…' : action === 'create' ? 'Crear cuenta' : 'Confirmar cambio'}</Button>
        <Button disabled={pending} type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
      </div>
    </form>
  </Modal>;
}
