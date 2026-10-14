import { ShieldCheck } from 'lucide-react';
import { Card } from '../../../components/ui/Card';
import { PageHeader } from '../../../components/ui/PageHeader';

export function SecretarioDashboardPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Secretaría"
        title="Consulta institucional"
        description="La expedición de certificados es automática y no requiere aprobación de secretaría."
      />
      <Card title="Flujo automático" description="El funcionario solicita y obtiene su certificado sin intervención humana.">
        <div className="flex items-start gap-3 text-sm text-muted">
          <ShieldCheck className="mt-0.5 shrink-0 text-villavoGreen" size={20} />
          <p>Secretaría no aprueba solicitudes, no valida comprobantes y no genera documentos manualmente.</p>
        </div>
      </Card>
    </div>
  );
}
