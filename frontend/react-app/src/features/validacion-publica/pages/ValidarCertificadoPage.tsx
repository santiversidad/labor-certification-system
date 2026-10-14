import { useQuery } from '@tanstack/react-query';
import { SearchCheck } from 'lucide-react';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { PageHeader } from '../../../components/ui/PageHeader';
import { ValidationResultCard } from '../components/ValidationResultCard';
import { validacionPublicaService } from '../services/validacionPublica.service';

export function ValidarCertificadoPage() {
  const { token = '' } = useParams();
  const [code, setCode] = useState(token);
  const [submittedCode, setSubmittedCode] = useState(token);
  const query = useQuery({
    queryKey: ['validacion-publica', submittedCode],
    queryFn: () => validacionPublicaService.validate(submittedCode),
    enabled: Boolean(submittedCode),
  });

  return (
    <div className="app-page max-w-4xl">
      <PageHeader eyebrow="Consulta pública" title="Validar certificado" description="Compruebe la vigencia e integridad de una certificación emitida por la Alcaldía de Villavicencio." />
      <section className="surface-section p-5 sm:p-7">
        <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); setSubmittedCode(code.trim()); }}>
          <div className="flex-1"><Input label="Código de validación" onChange={(event) => setCode(event.target.value)} placeholder="Ingrese el código del documento" value={code} /></div>
          <Button disabled={!code.trim() || query.isFetching} icon={<SearchCheck size={17} />} type="submit">{query.isFetching ? 'Validando...' : 'Validar certificado'}</Button>
        </form>
      </section>
      {query.isLoading ? <LoadingState label="Validando certificado..." /> : null}
      {query.isError ? <ErrorState message="No fue posible validar el certificado. Revise el código e intente nuevamente." /> : null}
      {query.data ? <ValidationResultCard result={query.data.data} /> : null}
    </div>
  );
}
