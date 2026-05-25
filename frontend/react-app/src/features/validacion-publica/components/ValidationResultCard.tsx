import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export function ValidationResultCard({ result }: { result: ValidacionCertificado }) {
  return (
    <Card title="Resultado de validacion" description="Consulta publica preparada para consumir el endpoint de Laravel.">
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted">Estado</dt>
          <dd><Badge tone={result.valido ? 'green' : 'red'}>{result.valido ? 'Valido' : 'No valido'}</Badge></dd>
        </div>
        <div>
          <dt className="text-muted">Codigo</dt>
          <dd className="font-medium text-text">{result.codigoValidacion}</dd>
        </div>
        <div>
          <dt className="text-muted">Funcionario</dt>
          <dd className="font-medium text-text">{result.funcionario}</dd>
        </div>
        <div>
          <dt className="text-muted">Cargo</dt>
          <dd className="font-medium text-text">{result.cargo}</dd>
        </div>
      </dl>
    </Card>
  );
}
