import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { Eye, EyeOff, LogIn, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Alert } from '../../../components/ui/Alert';
import { authStorage } from '../../../lib/auth/authStorage';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema';
import { authService } from '../services/auth.service';
import { useAuth } from '../hooks/useAuth';

export function LoginForm() {
  const navigate = useNavigate();
  const location = useLocation();
  const { isValidating } = useAuth();
  const [showPassword, setShowPassword] = useState(false);
  const { register, handleSubmit, formState: { errors } } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { username: '', password: '' },
  });

  const loginMutation = useMutation({
    mutationFn: authService.login,
    onSuccess: (response) => {
      authStorage.setSession({ user: response.data.user });
      navigate(response.data.user.must_change_password ? '/cambiar-contrasena' : getHomePath(response.data.user), { replace: true });
    },
  });

  return (
    <div>
      <div className="mb-7">
        <p className="eyebrow">Acceso seguro</p>
        <h1 className="mt-2 text-3xl font-bold tracking-[-0.035em] text-text">Iniciar sesión</h1>
        <p className="mt-2 text-sm leading-6 text-muted">Los funcionarios ingresan con su número de documento. Los usuarios internos utilizan su usuario institucional.</p>
      </div>
      {new URLSearchParams(location.search).get('sesion') === 'expirada' ? <Alert announce className="mb-4" variant="warning" title="Sesión expirada">Inicie sesión nuevamente para continuar.</Alert> : null}
      <form className="space-y-5" noValidate onSubmit={handleSubmit((values) => { if (!isValidating) loginMutation.mutate(values); })}>
        <Input autoComplete="username" error={errors.username?.message} label="Usuario" {...register('username')} />
        <div className="relative">
          <Input autoComplete="current-password" error={errors.password?.message} label="Contraseña" type={showPassword ? 'text' : 'password'} {...register('password')} />
          <button aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'} className="absolute right-2 top-[34px] rounded p-2 text-muted hover:bg-surface-muted hover:text-text" onClick={() => setShowPassword((value) => !value)} type="button">{showPassword ? <EyeOff size={18} /> : <Eye size={18} />}</button>
        </div>
        {loginMutation.isError ? <Alert variant="error">{getErrorMessage(loginMutation.error)}</Alert> : null}
        <Button className="w-full" disabled={loginMutation.isPending || isValidating} icon={<LogIn size={16} />} type="submit">
          {loginMutation.isPending ? 'Ingresando...' : 'Ingresar'}
        </Button>
      </form>
      <p className="mt-5 flex items-center justify-center gap-2 text-xs text-muted"><ShieldCheck size={15} /> Utilice su cuenta personal y cierre sesión al finalizar.</p>

    </div>
  );
}
