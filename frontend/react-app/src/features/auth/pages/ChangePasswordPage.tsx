import { useMutation } from '@tanstack/react-query';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { authStorage } from '../../../lib/auth/authStorage';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { useAuth } from '../hooks/useAuth';
import { authService } from '../services/auth.service';

export function ChangePasswordPage() {
  const navigate = useNavigate();
  const { session, user } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const mutation = useMutation({
    mutationFn: authService.changePassword,
    onSuccess: (response) => {
      if (session) authStorage.setSession({ ...session, user: response.data });
      navigate(getHomePath(response.data), { replace: true });
    },
  });

  if (!user) return <Navigate replace to="/login" />;
  if (!user.must_change_password) return <Navigate replace to={getHomePath(user)} />;

  return (
    <main className="grid min-h-screen place-items-center bg-slate-950 px-4 py-10">
      <section className="w-full max-w-lg rounded-2xl bg-white p-7 shadow-2xl shadow-black/20 sm:p-10">
        <div className="mb-8 flex items-start gap-4">
          <span className="rounded-xl bg-blue-50 p-3 text-govBlue"><ShieldCheck size={28} /></span>
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-govBlue">Primer ingreso</p>
            <h1 className="mt-2 text-2xl font-semibold text-text">Cambie su contraseña temporal</h1>
            <p className="mt-2 text-sm leading-6 text-muted">Antes de solicitar certificaciones debe definir una contraseña personal.</p>
          </div>
        </div>
        <form className="space-y-4" onSubmit={(event) => {
          event.preventDefault();
          mutation.mutate({ current_password: currentPassword, password, password_confirmation: confirmation });
        }}>
          <Input label="Contraseña temporal" onChange={(event) => setCurrentPassword(event.target.value)} type="password" value={currentPassword} />
          <Input label="Nueva contraseña" onChange={(event) => setPassword(event.target.value)} type="password" value={password} />
          <Input label="Confirmar nueva contraseña" onChange={(event) => setConfirmation(event.target.value)} type="password" value={confirmation} />
          <p className="text-xs leading-5 text-muted">Use al menos 12 caracteres, mayúsculas, minúsculas y números. No puede ser su cédula.</p>
          {mutation.isError ? <p className="text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
          <Button className="w-full" disabled={mutation.isPending} icon={<KeyRound size={17} />} type="submit">
            {mutation.isPending ? 'Actualizando…' : 'Guardar y continuar'}
          </Button>
        </form>
      </section>
    </main>
  );
}
