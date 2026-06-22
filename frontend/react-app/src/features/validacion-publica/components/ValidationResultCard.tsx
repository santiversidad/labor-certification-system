import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export function ValidationResultCard({ result }: { result: ValidacionCertificado }) {
  return (
    <Card title="Resultado de validación" description="Verificación pública del certificado.">
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted">Estado</dt>
          <dd>
            <Badge tone={result.valido ? 'green' : 'red'}>
              {result.valido ? 'Válido' : 'No válido'}
            </Badge>
          </dd>
        </div>
        <div>
          <dt className="text-muted">Código único</dt>
          <dd className="font-medium text-text">{result.codigo_unico ?? 'No encontrado'}</dd>
        </div>
        {result.funcionario_titular ? (
          <div>
            <dt className="text-muted">Funcionario titular</dt>
            <dd className="font-medium text-text">{result.funcionario_titular.nombre_completo}</dd>
          </div>
        ) : null}
        {result.tipo_certificado ? (
          <div>
            <dt className="text-muted">Tipo</dt>
            <dd className="font-medium text-text">{result.tipo_certificado}</dd>
          </div>
        ) : null}
        {result.fecha_expedicion ? (
          <div>
            <dt className="text-muted">Fecha expedición</dt>
            <dd className="font-medium text-text">{result.fecha_expedicion}</dd>
          </div>
        ) : null}
      </dl>
      {result.mensaje ? <p className="mt-4 rounded-md bg-background px-4 py-3 text-sm text-muted">{result.mensaje}</p> : null}
    </Card>
  );
}
