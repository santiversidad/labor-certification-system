import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Send } from 'lucide-react';
import { useForm, useWatch } from 'react-hook-form';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { getErrorMessage } from '../../../lib/utils/errors';
import { solicitudSchema, type SolicitudFormValues } from '../schemas/solicitud.schema';
import { solicitudesService } from '../services/solicitudes.service';

export function SolicitudForm() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const modalidadInicial = searchParams.get('modalidad') === 'con_salario';
  const { register, handleSubmit, setValue, control, formState: { errors } } = useForm<SolicitudFormValues>({
    resolver: zodResolver(solicitudSchema),
    defaultValues: { tipo_certificado: 'laboral', requiere_salario: modalidadInicial, observaciones: '' },
  });
  const requiereSalario = useWatch({ control, name: 'requiere_salario' });

  const disponibilidadQuery = useQuery({
    queryKey: ['disponibilidad-certificacion'],
    queryFn: solicitudesService.disponibilidad,
  });
  const disponibilidad = disponibilidadQuery.data?.data;
  const modalidadDisponible = requiereSalario
    ? disponibilidad?.con_salario.puede_solicitar
    : disponibilidad?.sin_salario.puede_solicitar;

  const createMutation = useMutation({
    mutationFn: solicitudesService.create,
    onSuccess: async (response) => {
      sessionStorage.setItem('ultima_solicitud_radicado', response.data.radicado);
      await queryClient.invalidateQueries({ queryKey: ['disponibilidad-certificacion'] });
      navigate('/app/solicitudes/confirmacion', {
        replace: true,
        state: {
          radicado: response.data.radicado,
          requiereSalario: response.data.requiere_salario,
          fechaRadicacion: response.data.created_at,
        },
      });
    },
  });

  return (
    <Card title="Modalidad de la certificación" description="La modalidad seleccionada queda registrada expresamente en la solicitud.">
      <form className="space-y-6" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
        <input type="hidden" {...register('tipo_certificado')} />

        <div className="grid gap-4 md:grid-cols-2">
          <button
            className={`rounded-md border p-4 text-left transition ${!requiereSalario ? 'border-govBlue bg-blue-50 ring-2 ring-govBlue/20' : 'border-border bg-surface'}`}
            onClick={() => setValue('requiere_salario', false, { shouldValidate: true })}
            type="button"
          >
            <span className="block font-semibold text-text">SIN salario</span>
            <span className="mt-1 block text-sm text-muted">No incluirá información salarial.</span>
            {disponibilidad && !disponibilidad.sin_salario.puede_solicitar ? (
              <span className="mt-3 block text-xs font-medium text-villavoRed">
                Cupo consumido. Disponible el {disponibilidad.sin_salario.proxima_fecha_disponible}.
              </span>
            ) : null}
          </button>
          <button
            className={`rounded-md border p-4 text-left transition ${requiereSalario ? 'border-govBlue bg-blue-50 ring-2 ring-govBlue/20' : 'border-border bg-surface'}`}
            onClick={() => setValue('requiere_salario', true, { shouldValidate: true })}
            type="button"
          >
            <span className="block font-semibold text-text">CON salario</span>
            <span className="mt-1 block text-sm text-muted">Usará la información salarial institucional vigente.</span>
            {disponibilidad && !disponibilidad.con_salario.puede_solicitar ? (
              <span className="mt-3 block text-xs font-medium text-villavoRed">
                Cupo consumido. Disponible el {disponibilidad.con_salario.proxima_fecha_disponible}.
              </span>
            ) : null}
          </button>
        </div>

        <p className="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-govBlue">
          Puede solicitar máximo una certificación con salario y una sin salario por mes.
        </p>

        <label className="block space-y-1 text-sm">
          <span className="font-medium text-text">Observaciones (opcional)</span>
          <textarea
            className="min-h-28 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none transition placeholder:text-muted focus:border-govBlue focus:ring-2 focus:ring-govBlue/20"
            placeholder="Indique la entidad destino u otra información necesaria para el trámite."
            {...register('observaciones')}
          />
          {errors.observaciones?.message ? <span className="text-xs text-villavoRed">{errors.observaciones.message}</span> : null}
        </label>

        {createMutation.isError ? (
          <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-villavoRed">
            {getErrorMessage(createMutation.error)}
          </div>
        ) : null}

        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Link to="/app/inicio">
            <Button className="w-full sm:w-auto" icon={<ArrowLeft size={16} />} type="button" variant="secondary">Volver</Button>
          </Link>
          <Button
            className="w-full sm:w-auto"
            disabled={createMutation.isPending || disponibilidadQuery.isLoading || modalidadDisponible === false}
            icon={<Send size={16} />}
            type="submit"
          >
            {createMutation.isPending ? 'Radicando...' : 'Radicar solicitud'}
          </Button>
        </div>
      </form>
    </Card>
  );
}
