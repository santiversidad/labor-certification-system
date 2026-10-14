import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Alert } from '../../../components/ui/Alert';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Textarea } from '../../../components/ui/Textarea';
import { getErrorMessage } from '../../../lib/utils/errors';
import { paymentWorkflowService } from '../services/paymentWorkflow.service';

export function ReceiptUploadForm({ id, rejected, maxKb = 5120 }: { id: number; rejected: boolean; maxKb?: number }) {
  const client = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [referencia, setReferencia] = useState('');
  const [observaciones, setObservaciones] = useState('');
  const [error, setError] = useState('');
  const mutation = useMutation({ mutationFn: () => paymentWorkflowService.upload(id, file!, referencia, observaciones), onSuccess: async () => {
    await client.invalidateQueries({ queryKey: ['solicitud', String(id)] });
    await client.invalidateQueries({ queryKey: ['solicitudes'] });
  } });
  function submit(event: FormEvent) {
    event.preventDefault(); setError('');
    if (!file) { setError('Seleccione un comprobante.'); return; }
    const formats: Record<string, string[]> = { 'application/pdf': ['pdf'], 'image/jpeg': ['jpg', 'jpeg'], 'image/png': ['png'] };
    if (!formats[file.type]?.includes(file.name.split('.').pop()?.toLowerCase() ?? '') || file.size > maxKb * 1024) {
      setError(`Adjunte PDF, JPG/JPEG o PNG de máximo ${maxKb / 1024} MB.`); return;
    }
    if (!mutation.isPending) mutation.mutate();
  }
  return <form className="surface-section space-y-4 p-5 sm:p-7" onSubmit={submit}>
    <h2 className="text-lg font-bold">{rejected ? 'Adjuntar nuevo comprobante' : 'Adjuntar comprobante'}</h2>
    <Alert title="Información oficial de pago pendiente" variant="warning">No hay tarifa ni instrucciones oficiales de pago configuradas. Consulte a la entidad antes de pagar. La referencia interna identifica la solicitud.</Alert>
    <Input accept=".pdf,.jpg,.jpeg,.png" label="Comprobante de pago" onChange={(e) => { setFile(e.target.files?.[0] ?? null); setError(''); }} type="file" />
    <p className="text-sm text-muted">PDF, JPG/JPEG o PNG. Máximo {maxKb / 1024} MB. Los comprobantes anteriores se conservarán.</p>
    <Input label="Referencia del pago (opcional)" maxLength={150} onChange={(e) => setReferencia(e.target.value)} value={referencia} />
    <Textarea label="Observación del pago (opcional)" maxLength={500} onChange={(e) => setObservaciones(e.target.value)} value={observaciones} />
    {error || mutation.isError ? <Alert title="No fue posible enviar el comprobante" variant="error">{error || getErrorMessage(mutation.error)}</Alert> : null}
    <Button disabled={!file || mutation.isPending} type="submit">{mutation.isPending ? 'Enviando...' : 'Enviar comprobante'}</Button>
  </form>;
}
