import { useEffect, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { getHomePath } from '../../../lib/auth/permissions';
import { getErrorMessage } from '../../../lib/utils/errors';
import { useAuth } from '../hooks/useAuth';
import { authService } from '../services/auth.service';
import { RecoveryCodesModal } from '../components/RecoveryCodesModal';

const invalidCode = 'El código no es válido o ya cambió. Revise el código actual de su aplicación e inténtelo nuevamente.';

export function MfaPage() {
  const { user, updateUser, logout } = useAuth();
  const navigate = useNavigate();
  // Enrollment material lives only in this mounted screen, never browser storage.
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [setupAttempt, setSetupAttempt] = useState(0);
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState(false);
  const [codes, setCodes] = useState<string[]>([]);
  const [pending, setPending] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState('');
  const [pageError, setPageError] = useState('');
  const [copyNotice, setCopyNotice] = useState('');
  // StrictMode replays effects in development; share setup until an explicit
  // retry so a replay cannot create concurrent enrollment requests.
  const setupRequest = useRef<ReturnType<typeof authService.setupMfa> | null>(null);
  const enrollment = !user?.mfa_enabled;
  useEffect(() => {
    let live = true;
    if (user?.mfa_pending && !user.must_change_password && !user.mfa_enabled) {
      setupRequest.current ??= authService.setupMfa();
      void setupRequest.current.then((result) => {
        if (live) setSetup({ secret: result.data.secret, otpauth_uri: result.data.otpauth_uri });
      }).catch((failure) => { if (live) setPageError(getErrorMessage(failure)); });
    }
    return () => { live = false; };
  }, [user?.mfa_pending, user?.must_change_password, user?.mfa_enabled, setupAttempt]);
  if (!user || user.tipo_cuenta !== 'backoffice') return <Navigate replace to="/login" />;
  if (user.must_change_password) return <Navigate replace to="/cambiar-contrasena" />;
  if (!user.mfa_pending && codes.length === 0) return <Navigate replace to={getHomePath(user)} />;
  return <div className="space-y-5">
    <header className="space-y-3">
      <p className="eyebrow">Seguridad de la cuenta</p>
      <h1 className="text-2xl font-bold text-text">{enrollment ? 'Configurar autenticación en dos pasos' : 'Verificar código'}</h1>
      <p className="text-sm leading-6 text-text-muted">{enrollment
        ? 'Agregue una segunda verificación para proteger su cuenta. Además de su contraseña, utilizará un código temporal generado en su celular.'
        : 'Introduzca el código de su aplicación autenticadora para completar el inicio de sesión.'}</p>
    </header>
    {pageError ? <Alert variant="error">{pageError}</Alert> : null}
    {enrollment ? <>
      <section aria-labelledby="mfa-step-1" className="space-y-2">
        <p className="eyebrow">Paso 1</p>
        <h2 id="mfa-step-1" className="font-semibold text-text">Abra una aplicación de autenticación</h2>
        <p className="text-sm leading-6 text-text-muted">Puede utilizar Google Authenticator, Microsoft Authenticator, 2FAS u otra aplicación compatible.</p>
      </section>
      <section aria-labelledby="mfa-step-2" className="space-y-3">
        <p className="eyebrow">Paso 2</p>
        <h2 id="mfa-step-2" className="font-semibold text-text">Escanee este código QR</h2>
        <p className="text-sm leading-6 text-text-muted">En su aplicación autenticadora, seleccione agregar una cuenta y escanee el código.</p>
        {setup ? <div data-testid="mfa-qr" role="img" aria-label="Código QR para agregar su cuenta a la aplicación autenticadora. También puede utilizar la clave manual."
          className="mx-auto w-full max-w-[220px] bg-white">
          <QRCodeSVG value={setup.otpauth_uri} size={220} marginSize={4} level="M" aria-hidden="true" className="block h-auto w-full" />
        </div> : <p role="status" className="text-sm text-text-muted">{pageError ? 'No se pudo preparar el código QR.' : 'Preparando el código QR…'}</p>}
        {!setup && pageError ? <Button variant="secondary" onClick={() => { setupRequest.current = null; setPageError(''); setSetupAttempt(setupAttempt + 1); }}>Volver a intentar</Button> : null}
        {setup ? <details className="text-sm" onToggle={() => setCopyNotice('')}>
          <summary className="cursor-pointer rounded py-2 font-semibold text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">No puedo escanear el código</summary>
          <div className="space-y-3 pt-2">
            <p className="leading-6 text-text-muted">Seleccione la opción para introducir una clave manual en su aplicación y copie la siguiente clave.</p>
            <Input autoComplete="off" data-testid="mfa-secret" label="Clave de configuración" readOnly value={setup.secret} className="font-mono text-xs" />
            <Button variant="secondary" onClick={() => {
              if (!navigator.clipboard) { setCopyNotice('Seleccione y copie la clave con el teclado.'); return; }
              void navigator.clipboard.writeText(setup.secret).then(() => setCopyNotice('Clave copiada'))
                .catch(() => setCopyNotice('Seleccione y copie la clave con el teclado.'));
            }}>Copiar clave</Button>
            {copyNotice ? <p role="status">{copyNotice}</p> : null}
          </div>
        </details> : null}
      </section>
    </> : null}
    <form noValidate className="space-y-4" aria-busy={pending} onSubmit={(event) => {
      event.preventDefault();
      if (pending || cancelling || (enrollment && !setup)) return;
      const value = code.trim();
      if (!value || (!recovery && !/^[0-9]{6}$/.test(value))) {
        setError(recovery ? 'Ingrese uno de sus códigos de recuperación.' : 'Ingrese el código de 6 dígitos que aparece en su aplicación.');
        document.getElementById('mfa-code')?.focus(); return;
      }
      setPending(true); setError(''); setPageError('');
      void authService.verifyMfa(value, enrollment).then((result) => {
        setupRequest.current = null;
        setCode(''); setSetup(null); setCodes(result.data.recovery_codes); updateUser(result.data.user);
        if (!result.data.recovery_codes.length) navigate(getHomePath(result.data.user), { replace: true });
      }).catch((failure: unknown) => {
        const status = (failure as { response?: { status?: number } })?.response?.status;
        setError(status === 422 ? (recovery ? 'El código de recuperación no es válido o ya fue utilizado. Revise otro de sus códigos guardados.' : invalidCode) : getErrorMessage(failure));
        document.getElementById('mfa-code')?.focus();
      }).finally(() => setPending(false));
    }}>
      {enrollment ? <div className="space-y-2">
        <p className="eyebrow">Paso 3</p>
        <h2 className="font-semibold text-text">Ingrese el código de 6 dígitos</h2>
        <p className="text-sm leading-6 text-text-muted">Después de agregar la cuenta, su aplicación mostrará un código temporal. Escríbalo aquí para finalizar la configuración.</p>
      </div> : null}
      <Input id="mfa-code" autoFocus={!enrollment} autoComplete={recovery ? 'off' : 'one-time-code'} inputMode={recovery ? 'text' : 'numeric'}
        label={recovery ? 'Código de recuperación' : enrollment ? 'Código de 6 dígitos' : 'Código de autenticación'} maxLength={recovery ? 64 : 6}
        placeholder={recovery ? undefined : '000000'} pattern={recovery ? undefined : '[0-9]{6}'} required
        value={code} error={error || undefined} onChange={(event) => { setCode(recovery ? event.target.value : event.target.value.replace(/[^0-9]/g, '')); setError(''); }} />
      <Button className="w-full" disabled={pending || cancelling || (enrollment && !setup)} type="submit">{pending ? 'Verificando…' : enrollment ? 'Activar autenticación en dos pasos' : 'Verificar y continuar'}</Button>
    </form>
    {!enrollment ? <Button variant="secondary" disabled={pending || cancelling} onClick={() => { setRecovery(!recovery); setCode(''); setError(''); }}>{recovery ? 'Usar aplicación autenticadora' : 'Usar código de recuperación'}</Button> : null}
    <Button className="w-full" variant="secondary" disabled={pending || cancelling} onClick={() => {
      setCancelling(true); setPageError('');
      void authService.logout().then(() => { setupRequest.current = null; setSetup(null); setCode(''); setCodes([]); logout(); navigate('/login', { replace: true }); })
        .catch(() => setPageError('No fue posible cerrar la sesión. Inténtelo nuevamente para cancelar la configuración de forma segura.'))
        .finally(() => setCancelling(false));
    }}>{cancelling ? 'Cerrando sesión…' : 'Cancelar y cerrar sesión'}</Button>
    {codes.length > 0 ? <RecoveryCodesModal codes={codes} onClose={() => { setCodes([]); navigate(getHomePath(user), { replace: true }); }} /> : null}
  </div>;
}
