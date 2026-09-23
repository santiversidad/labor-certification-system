import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { LoadingState } from '../../../components/feedback/LoadingState';
import { EmptyState } from '../../../components/ui/EmptyState';
import { PageHeader } from '../../../components/ui/PageHeader';
import { CertificadosTable } from '../components/CertificadosTable';
import { certificadosService } from '../services/certificados.service';
import type { TipoCertificado } from '../../solicitudes/types/solicitud.types';

export function CertificadosPage() {
  const [tipo, setTipo] = useState<'' | TipoCertificado>('');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['certificados'],
    queryFn: certificadosService.list,
  });

  if (isLoading) {
    return <LoadingState />;
  }

  if (isError || !data) {
    return <ErrorState />;
  }

  const certificados = data.data ?? [];
  const certificadosFiltrados = tipo
    ? certificados.filter((certificado) => certificado.solicitud?.tipo_certificado === tipo)
    : certificados;

  return (
    <div className="space-y-6">
      <PageHeader title="Certificados" description="Consulte certificados generados, estado y solicitud asociada." />
      <Card title="Certificados disponibles">
        <label className="mb-5 block max-w-xs text-sm font-medium text-text">
          Tipo de certificado
          <select className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-govBlue" onChange={(event) => setTipo(event.target.value as '' | TipoCertificado)} value={tipo}>
            <option value="">Todos</option>
            <option value="sencillo">Sencillo</option>
            <option value="funciones">Con funciones</option>
          </select>
        </label>
        {certificadosFiltrados.length ? <CertificadosTable certificados={certificadosFiltrados} /> : <EmptyState title="Sin certificados" description="No hay certificados que coincidan con el filtro seleccionado." />}
      </Card>
    </div>
  );
}
