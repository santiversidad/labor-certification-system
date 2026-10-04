import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { useAuth } from '../hooks/useAuth';
import { authService } from '../services/auth.service';
import { RecoveryCodesModal } from '../components/RecoveryCodesModal';

export function MfaPage() {
  const { user, updateUser, logout } = useAuth();
  const navigate = useNavigate();
  const [secret, setSecret] = useState('');
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState(false);
  const [codes, setCodes] = useState<string[]>([]);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState('');
  const enrollment = !user?.mfa_enabled;
  useEffect(() => {
    let live = true;
    if (user?.mfa_pending && !user.must_change_password && !user.mfa_enabled) {
      void authService.setupMfa().then((result) => { if (live) setSecret(result.data.secret); })
        .catch((failure) => { if (live) setError(getErrorMessage(failure)); });
    }
    return () => { live = false; };
  }, [user?.mfa_pending, user?.must_change_password, user?.mfa_enabled]);
  if (!user || user.tipo_cuenta !== 'backoffice') return <Navigate replace to="/login" />;
  if (user.must_change_password) return <Navigate replace to="/cambiar-contrasena" />;
  if (!user.mfa_pending && codes.length === 0) return <Navigate replace to={getHomePath(user)} />;
  return <div className="space-y-5">
    <p className="eyebrow">Seguridad de la cuenta</p>
    <h1 className="text-2xl font-bold text-text">{enrollment ? 'Configurar autenticación en dos pasos' : 'Verificar código'}</h1>
    <Alert variant="info">{enrollment ? 'Añada una cuenta TOTP en su aplicación autenticadora con esta clave. Introduzca un código para confirmar. No podrá acceder al panel hasta completar este paso.' : 'Introduzca el código de su aplicación autenticadora para completar el inicio de sesión.'}</Alert>
    {enrollment ? <Input autoComplete="off" data-testid="mfa-secret" label="Clave de configuración" readOnly value={secret} /> : null}
    <form className="space-y-4" onSubmit={(event) => {
      event.preventDefault(); setPending(true); setError('');
      void authService.verifyMfa(code.trim(), enrollment).then((result) => {
        setCode(''); setSecret(''); setCodes(result.data.recovery_codes); updateUser(result.data.user);
        if (!result.data.recovery_codes.length) navigate(getHomePath(result.data.user), { replace: true });
      }).catch((failure) => setError(getErrorMessage(failure))).finally(() => setPending(false));
    }}>
      <Input autoFocus autoComplete={recovery ? 'off' : 'one-time-code'} inputMode={recovery ? 'text' : 'numeric'}
        label={recovery ? 'Código de recuperación' : 'Código de autenticación'} maxLength={recovery ? 64 : 6}
        pattern={recovery ? undefined : '[0-9]{6}'} required value={code} error={error || undefined} onChange={(event) => setCode(event.target.value)} />
      {error ? <Alert variant="error">{error}</Alert> : null}
      <Button disabled={pending || (enrollment && !secret)} type="submit">{pending ? 'Verificando…' : enrollment ? 'Confirmar autenticación' : 'Verificar y continuar'}</Button>
    </form>
    {!enrollment ? <Button variant="secondary" onClick={() => { setRecovery(!recovery); setCode(''); }}>{recovery ? 'Usar aplicación autenticadora' : 'Usar código de recuperación'}</Button> : null}
    <Button variant="secondary" onClick={() => { void authService.logout().finally(() => { logout(); navigate('/login', { replace: true }); }); }}>Cancelar y cerrar sesión</Button>
    {codes.length > 0 ? <RecoveryCodesModal codes={codes} onClose={() => { setCodes([]); navigate(getHomePath(user), { replace: true }); }} /> : null}
  </div>;
}
