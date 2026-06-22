import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { LogIn } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { getHomePath } from '../../../lib/auth/permissions';
import { authStorage } from '../../../lib/auth/authStorage';
import { getErrorMessage } from '../../../lib/utils/errors';
import { authService } from '../services/auth.service';
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema';

export function LoginForm() {
  const navigate = useNavigate();
  const { register, handleSubmit, formState: { errors } } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      cedula: '000000003',
      password: 'password',
    },
  });

  const loginMutation = useMutation({
    mutationFn: authService.login,
    onSuccess: (response) => {
      authStorage.setSession(response.data);
      navigate(getHomePath(response.data.user), { replace: true });
    },
  });

  return (
    <Card title="Iniciar sesion" description="Ingrese cedula y contrasena para acceder al sistema.">
      <form className="space-y-4" onSubmit={handleSubmit((values) => loginMutation.mutate(values))}>
        <Input error={errors.cedula?.message} label="Cedula" {...register('cedula')} />
        <Input error={errors.password?.message} label="Contrasena" type="password" {...register('password')} />
        {loginMutation.isError ? <p className="text-sm text-villavoRed">{getErrorMessage(loginMutation.error)}</p> : null}
        <Button className="w-full" disabled={loginMutation.isPending} icon={<LogIn size={16} />} type="submit">
          {loginMutation.isPending ? 'Ingresando...' : 'Ingresar'}
        </Button>
      </form>
      <div className="mt-5 rounded-md bg-background p-3 text-xs text-muted space-y-1">
        <p><span className="font-semibold text-text">000000003</span> / password — Funcionario</p>
        <p><span className="font-semibold text-text">000000002</span> / password — Secretario</p>
        <p><span className="font-semibold text-text">000000001</span> / password — Administrador</p>
      </div>
    </Card>
  );
}
