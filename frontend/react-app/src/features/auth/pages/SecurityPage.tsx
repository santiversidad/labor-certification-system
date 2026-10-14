import { useState } from 'react';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { getErrorMessage } from '../../../lib/utils/errors';
import { authService } from '../services/auth.service';
import { RecoveryCodesModal } from '../components/RecoveryCodesModal';

export function SecurityPage() {
  const [password, setPassword] = useState(''); const [code, setCode] = useState('');
  const [codes, setCodes] = useState<string[]>([]); const [message, setMessage] = useState('');
  const [error, setError] = useState(''); const [pending, setPending] = useState(false);
  return <div className="max-w-xl space-y-5">
    <h1 className="text-2xl font-bold">Seguridad de la cuenta</h1>
    <Alert variant="info">Confirme su contraseña y un código nuevo de autenticación o recuperación. La confirmación permite acciones críticas durante cinco minutos.</Alert>
    <form className="space-y-4" onSubmit={(event) => {
      event.preventDefault(); setPending(true); setError('');
      void authService.reauthenticate(password, code).then(() => { setPassword(''); setCode(''); setMessage('Autenticación confirmada por cinco minutos.'); })
        .catch((failure) => setError(getErrorMessage(failure))).finally(() => setPending(false));
    }}>
      <Input autoComplete="current-password" label="Contraseña de confirmación" maxLength={128} type="password" required value={password} onChange={(event) => setPassword(event.target.value)} />
      <Input autoComplete="one-time-code" label="Código de confirmación" maxLength={64} required value={code} onChange={(event) => setCode(event.target.value)} />
      <Button type="submit" disabled={pending}>Confirmar identidad</Button>
    </form>
    {error ? <Alert variant="error">{error}</Alert> : null}
    {message ? <Alert variant="success">{message}</Alert> : null}
    <Button variant="secondary" disabled={pending} onClick={() => {
      setPending(true); setError('');
      void authService.regenerateRecoveryCodes().then((result) => setCodes(result.data.recovery_codes))
        .catch((failure) => setError(getErrorMessage(failure))).finally(() => setPending(false));
    }}>Regenerar códigos de recuperación</Button>
    <p className="text-sm text-muted">La regeneración revoca todos los códigos anteriores.</p>
    {codes.length ? <RecoveryCodesModal codes={codes} onClose={() => setCodes([])} /> : null}
  </div>;
}
