import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { LogIn } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { authStorage } from '../../../lib/auth/authStorage';
import { getErrorMessage } from '../../../lib/utils/errors';
import { authService } from '../services/auth.service';
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema';

export function LoginForm() {
  const navigate = useNavigate();
  const { register, handleSubmit, formState: { errors } } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: 'funcionario@villavicencio.gov.co',
      password: 'demo',
    },
  });

  const loginMutation = useMutation({
    mutationFn: authService.login,
    onSuccess: (response) => {
      authStorage.setSession(response.data);
      const target = response.data.user.role === 'administrador' ? '/admin/dashboard' : '/app/dashboard';
      navigate(target, { replace: true });
    },
  });

  return (
    <Card title="Iniciar sesion" description="Use un usuario mock; cualquier contrasena no vacia es valida en esta fase.">
      <form className="space-y-4" onSubmit={handleSubmit((values) => loginMutation.mutate(values))}>
        <Input error={errors.email?.message} label="Correo institucional" type="email" {...register('email')} />
        <Input error={errors.password?.message} label="Contrasena" type="password" {...register('password')} />
        {loginMutation.isError ? <p className="text-sm text-villavoRed">{getErrorMessage(loginMutation.error)}</p> : null}
        <Button className="w-full" disabled={loginMutation.isPending} icon={<LogIn size={16} />} type="submit">
          {loginMutation.isPending ? 'Ingresando...' : 'Ingresar'}
        </Button>
      </form>
      <div className="mt-5 rounded-md bg-background p-3 text-xs text-muted">
        <p>funcionario@villavicencio.gov.co</p>
        <p>secretario@villavicencio.gov.co</p>
        <p>admin@villavicencio.gov.co</p>
      </div>
    </Card>
  );
}
