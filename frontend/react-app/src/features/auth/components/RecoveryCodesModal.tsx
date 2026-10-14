import { useState } from 'react';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Modal } from '../../../components/ui/Modal';

export function RecoveryCodesModal({ codes, onClose }: { codes: string[]; onClose: () => void }) {
  const [notice, setNotice] = useState('');
  return <Modal open title="Códigos de recuperación" onClose={onClose}>
    <div className="space-y-4">
      <p className="text-sm text-text-muted">Estos códigos le permiten acceder a su cuenta si pierde el celular o no puede usar su aplicación autenticadora.</p>
      <Alert variant="warning">Guarde estos códigos en un lugar seguro. Cada código sirve una sola vez y solo se muestra ahora. No los comparta.</Alert>
      <pre data-testid="recovery-codes" className="overflow-x-auto rounded border border-border p-3 text-xs">{codes.join('\n')}</pre>
      {notice ? <p role="status">{notice}</p> : null}
      <div className="flex flex-wrap gap-3">
        <Button onClick={() => { void navigator.clipboard?.writeText(codes.join('\n')).then(() => setNotice('Códigos copiados.')).catch(() => setNotice('Seleccione y copie los códigos con el teclado.')); }}>Copiar códigos</Button>
        <Button variant="secondary" onClick={() => {
          const url = URL.createObjectURL(new Blob([codes.join('\n')], { type: 'text/plain' }));
          const link = document.createElement('a'); link.href = url; link.download = 'codigos-recuperacion.txt'; link.click(); URL.revokeObjectURL(url);
        }}>Guardar códigos</Button>
        <Button onClick={onClose}>Ya los guardé, continuar</Button>
      </div>
    </div>
  </Modal>;
}
