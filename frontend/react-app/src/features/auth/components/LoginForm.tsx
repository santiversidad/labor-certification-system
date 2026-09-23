import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { Eye, EyeOff, LogIn, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { authStorage } from '../../../lib/auth/authStorage';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema';
import { authService } from '../services/auth.service';

export function LoginForm() {
  const navigate = useNavigate();
  const [showPassword, setShowPassword] = useState(false);
  const { register, handleSubmit, formState: { errors } } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: import.meta.env.DEV
      ? { cedula: '000000003', password: 'password' }
      : { cedula: '', password: '' },
  });

  const loginMutation = useMutation({
    mutationFn: authService.login,
    onSuccess: (response) => {
      authStorage.setSession(response.data);
      navigate(response.data.user.must_change_password ? '/cambiar-contrasena' : getHomePath(response.data.user), { replace: true });
    },
  });

  return (
    <div>
      <div className="mb-7">
        <p className="eyebrow">Acceso seguro</p>
        <h1 className="mt-2 text-3xl font-bold tracking-[-0.035em] text-text">Iniciar sesión</h1>
        <p className="mt-2 text-sm leading-6 text-muted">Ingrese con las credenciales asignadas por Talento Humano.</p>
      </div>
      <form className="space-y-5" noValidate onSubmit={handleSubmit((values) => loginMutation.mutate(values))}>
        <Input autoComplete="username" error={errors.cedula?.message} inputMode="numeric" label="Cédula" {...register('cedula')} />
        <div className="relative">
          <Input autoComplete="current-password" error={errors.password?.message} label="Contraseña" type={showPassword ? 'text' : 'password'} {...register('password')} />
          <button aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'} className="absolute right-2 top-[34px] rounded p-2 text-muted hover:bg-surface-muted hover:text-text" onClick={() => setShowPassword((value) => !value)} type="button">{showPassword ? <EyeOff size={18} /> : <Eye size={18} />}</button>
        </div>
        {loginMutation.isError ? <p className="rounded-md border border-error/20 bg-error/5 p-3 text-sm text-error" role="alert">{getErrorMessage(loginMutation.error)}</p> : null}
        <Button className="w-full" disabled={loginMutation.isPending} icon={<LogIn size={16} />} type="submit">
          {loginMutation.isPending ? 'Ingresando...' : 'Ingresar'}
        </Button>
      </form>
      <p className="mt-5 flex items-center justify-center gap-2 text-xs text-muted"><ShieldCheck size={15} /> Sus datos viajan mediante una conexión segura.</p>
      {import.meta.env.DEV ? (
        <details className="mt-6 rounded-md border border-dashed border-border bg-surface-muted/50 p-3 text-xs text-muted" data-testid="demo-credentials">
          <summary className="cursor-pointer font-semibold text-text">Usuarios de desarrollo</summary>
          <div className="mt-2 space-y-1"><p><strong>000000003</strong> / password — Funcionario</p><p><strong>000000001</strong> / password — Administrador</p></div>
        </details>
      ) : null}
    </div>
  );
}
