import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import type { ValidacionCertificado } from '../types/validacionPublica.types';

export function ValidationResultCard({ result }: { result: ValidacionCertificado }) {
  const anulado = result.resultado === 'anulado';
  const mensaje = result.resultado === 'no_encontrado'
    ? 'Certificado no encontrado o código de validación inválido.'
    : result.resultado === 'integridad_comprometida'
      ? 'El documento no pudo validarse.'
      : anulado ? 'CERTIFICADO ANULADO' : result.mensaje;
  return (
    <Card title="Resultado de validación" description="Verificación pública del certificado.">
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt className="text-muted">Estado</dt><dd><Badge tone={result.valido ? 'green' : 'red'}>{anulado ? 'CERTIFICADO ANULADO' : result.valido ? 'Válido' : 'No válido'}</Badge></dd></div>
        <div><dt className="text-muted">Código único</dt><dd className="font-medium text-text">{result.codigo_unico ?? 'No encontrado'}</dd></div>
        {result.funcionario ? <div><dt className="text-muted">Funcionario titular</dt><dd className="font-medium text-text">{result.funcionario.nombre}</dd></div> : null}
        {result.cargo ? <div><dt className="text-muted">Cargo</dt><dd className="font-medium text-text">{result.cargo}</dd></div> : null}
        {result.tipo_certificado ? <div><dt className="text-muted">Tipo</dt><dd className="font-medium text-text">{result.tipo_certificado === 'funciones' ? 'Con funciones' : 'Sencillo'}</dd></div> : null}
        {result.fecha_generacion ? <div><dt className="text-muted">Fecha de generación</dt><dd className="font-medium text-text">{result.fecha_generacion}</dd></div> : null}
      </dl>
      {mensaje ? <p className="mt-4 rounded-md bg-background px-4 py-3 text-sm text-muted">{mensaje}</p> : null}
    </Card>
  );
}
