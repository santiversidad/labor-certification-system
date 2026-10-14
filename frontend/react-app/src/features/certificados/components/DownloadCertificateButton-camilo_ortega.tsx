import { Download } from 'lucide-react';
import { Button } from '../../../components/ui/Button';
import { useMutation } from '@tanstack/react-query';
import { getErrorMessage } from '../../../lib/utils/errors';
import { certificadosService } from '../services/certificados.service';

export function DownloadCertificateButton({ certificadoId, disabled = false }: { certificadoId: string | number; disabled?: boolean }) {
  const mutation = useMutation({ mutationFn: () => certificadosService.download(certificadoId) });

  return (
    <div>
      <Button
        disabled={disabled || mutation.isPending}
        icon={<Download size={16} />}
        onClick={() => mutation.mutate()}
        type="button"
        variant="secondary"
      >
        {mutation.isPending ? 'Descargando...' : 'Descargar'}
      </Button>
      {mutation.isError ? <p className="mt-2 max-w-64 text-xs text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
    </div>
  );
}
