import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export function ValidationResultCard({ result }: { result: ValidacionCertificado }) {
  return (
    <Card title="Resultado de validacion" description="La consulta publica solo muestra datos minimos no sensibles.">
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted">Estado</dt>
          <dd><Badge tone={result.valido ? 'green' : 'red'}>{result.valido ? 'Valido' : 'No valido'}</Badge></dd>
        </div>
        <div>
          <dt className="text-muted">Codigo</dt>
          <dd className="font-medium text-text">{result.codigoValidacion ?? 'No encontrado'}</dd>
        </div>
        <div><dt className="text-muted">Estado del certificado</dt><dd className="font-medium text-text">{result.estado}</dd></div>
        <div><dt className="text-muted">Fecha de generacion</dt><dd className="font-medium text-text">{result.fechaGeneracion ?? 'No disponible'}</dd></div>
        {result.funcionario ? <div><dt className="text-muted">Funcionario</dt><dd className="font-medium text-text">{result.funcionario}</dd></div> : null}
        {result.cargo ? <div><dt className="text-muted">Cargo</dt><dd className="font-medium text-text">{result.cargo}</dd></div> : null}
      </dl>
      {result.mensaje ? <p className="mt-4 rounded-md bg-background px-4 py-3 text-sm text-muted">{result.mensaje}</p> : null}
    </Card>
  );
}
