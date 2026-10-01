import { useMutation } from '@tanstack/react-query';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Alert } from '../../../components/ui/Alert';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { useAuth } from '../hooks/useAuth';
import { authService } from '../services/auth.service';

export function ChangePasswordPage() {
  const navigate = useNavigate();
  const { updateUser, user } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const mutation = useMutation({
    mutationFn: authService.changePassword,
    onSuccess: (response) => {
      updateUser(response.data);
      navigate(getHomePath(response.data), { replace: true });
    },
  });

  if (!user) return <Navigate replace to="/login" />;
  if (!user.must_change_password) return <Navigate replace to={getHomePath(user)} />;

  return (
    <div>
      <p className="eyebrow">Primer ingreso</p>
      <h1 className="mt-2 text-3xl font-bold tracking-tight text-text">Cambie su contraseña temporal</h1>
      <Alert className="mt-6" title="Cambio obligatorio de contraseña" variant="warning">Antes de solicitar certificaciones debe definir una contraseña personal. Use al menos 12 caracteres, mayúsculas, minúsculas y números. No use su número de cédula.</Alert>
        <form className="mt-6 space-y-4" onSubmit={(event) => {
          event.preventDefault();
          mutation.mutate({ current_password: currentPassword, password, password_confirmation: confirmation });
        }}>
          <Input label="Contraseña temporal" onChange={(event) => setCurrentPassword(event.target.value)} type="password" value={currentPassword} />
          <Input label="Nueva contraseña" onChange={(event) => setPassword(event.target.value)} type="password" value={password} />
          <Input label="Confirmar nueva contraseña" onChange={(event) => setConfirmation(event.target.value)} type="password" value={confirmation} />
          {mutation.isError ? <Alert title="No fue posible cambiar la contraseña" variant="error">{getErrorMessage(mutation.error)}</Alert> : null}
          <Button className="w-full" disabled={mutation.isPending} icon={<KeyRound size={17} />} type="submit">
            {mutation.isPending ? 'Actualizando…' : 'Guardar y continuar'}
          </Button>
        </form>
    </div>
  );
}
