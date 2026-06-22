import { Download } from 'lucide-react';
import { Button } from '../../../components/ui/Button';

export function DownloadCertificateButton({ url = '#', disabled = false }: { url?: string; disabled?: boolean }) {
  if (disabled) {
    return (
      <Button disabled icon={<Download size={16} />} type="button" variant="secondary">
        Descargar
      </Button>
    );
  }

  return (
    <a href={url}>
      <Button icon={<Download size={16} />} type="button" variant="secondary">
        Descargar
      </Button>
    </a>
  );
}
