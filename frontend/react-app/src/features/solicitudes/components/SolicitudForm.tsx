import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { ArrowLeft, Send } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ErrorState } from '../../../components/feedback/ErrorState';
import { useAuth } from '../../auth/hooks/useAuth';
import { solicitudSchema, type SolicitudFormValues } from '../schemas/solicitud.schema';
import { solicitudesService } from '../services/solicitudes.service';

export function SolicitudForm() {
  const { user } = useAuth();
  const { register, handleSubmit, reset, formState: { errors } } = useForm<SolicitudFormValues>({
    resolver: zodResolver(solicitudSchema),
    defaultValues: { tipo: 'laboral', incluyeSalario: false, observaciones: '' },
  });

  const createMutation = useMutation({
    mutationFn: solicitudesService.create,
    onSuccess: () => {
      reset({ tipo: 'laboral', incluyeSalario: false, observaciones: '' });
    },
  });

  return (
    <Card title="Datos de la solicitud" description="Formulario validado con React Hook Form y Zod; el envio usa el servicio mock de solicitudes.">
      <form className="space-y-6" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
        <section className="grid gap-4 rounded-md border border-border bg-background p-4 md:grid-cols-3">
          <div>
            <p className="text-xs font-semibold uppercase text-muted">Funcionario</p>
            <p className="mt-1 text-sm font-medium text-text">{user?.name ?? 'Funcionario mock'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted">Correo institucional</p>
            <p className="mt-1 text-sm font-medium text-text">{user?.email ?? 'funcionario@villavicencio.gov.co'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted">Dependencia</p>
            <p className="mt-1 text-sm font-medium text-text">{user?.dependencia ?? 'Secretaria Administrativa'}</p>
          </div>
        </section>

        <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
          <label className="block space-y-1 text-sm">
            <span className="font-medium text-text">Tipo de certificacion</span>
            <select className="min-h-10 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-govBlue focus:ring-2 focus:ring-govBlue/20" {...register('tipo')}>
              <option value="laboral">Certificacion laboral</option>
              <option value="salarial">Certificacion salarial</option>
              <option value="funciones">Certificacion de funciones</option>
            </select>
            <span className="text-xs text-muted">Seleccione el documento requerido para su tramite.</span>
          </label>

          <label className="flex min-h-10 items-start gap-3 rounded-md border border-border bg-surface p-3 text-sm">
            <input className="mt-1 h-4 w-4 rounded border-border text-govBlue focus:ring-govBlue" type="checkbox" {...register('incluyeSalario')} />
            <span>
              <span className="block font-medium text-text">Incluir salario</span>
              <span className="text-xs text-muted">Agrega informacion salarial cuando el certificado lo requiera.</span>
            </span>
          </label>
        </div>

        <label className="block space-y-1 text-sm">
          <span className="font-medium text-text">Observaciones</span>
          <textarea
            className="min-h-28 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none transition placeholder:text-muted focus:border-govBlue focus:ring-2 focus:ring-govBlue/20"
            placeholder="Indique detalles del tramite, entidad destino o informacion adicional."
            {...register('observaciones')}
          />
          {errors.observaciones?.message ? <span className="text-xs text-villavoRed">{errors.observaciones.message}</span> : null}
        </label>

        {createMutation.isError ? <ErrorState message="No fue posible crear la solicitud mock." /> : null}
        {createMutation.isSuccess ? (
          <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-villavoGreen">
            Solicitud enviada correctamente. El radicado mock quedo en estado pendiente.
          </div>
        ) : null}

        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Link to="/app/solicitudes">
            <Button className="w-full sm:w-auto" icon={<ArrowLeft size={16} />} type="button" variant="secondary">Volver</Button>
          </Link>
          <Button className="w-full sm:w-auto" disabled={createMutation.isPending} icon={<Send size={16} />} type="submit">
            {createMutation.isPending ? 'Enviando...' : 'Enviar solicitud'}
          </Button>
        </div>
      </form>
    </Card>
  );
}
