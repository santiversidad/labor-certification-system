import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { ValidationResultCard } from '../components/ValidationResultCard';
import { validacionPublicaService } from '../services/validacionPublica.service';

export function ValidarCertificadoPage() {
  const { token = '' } = useParams();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['validacion-publica', token],
    queryFn: () => validacionPublicaService.validate(token),
    enabled: Boolean(token),
  });

  if (isLoading) {
    return <LoadingState label="Validando certificado..." />;
  }

  if (isError || !data) {
    return <ErrorState message="No fue posible validar el certificado." />;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-text">Validacion publica de certificado</h1>
        <p className="mt-1 text-sm text-muted">Ruta publica preparada para validar certificados por token.</p>
      </div>
      <ValidationResultCard result={data.data} />
    </div>
  );
}
