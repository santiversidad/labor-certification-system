import { Download } from 'lucide-react';
import { Button } from '../../../components/ui/Button';

export function DownloadCertificateButton({ url = '#' }: { url?: string }) {
  return (
    <a href={url}>
      <Button icon={<Download size={16} />} type="button" variant="secondary">
        Descargar
      </Button>
    </a>
  );
}
