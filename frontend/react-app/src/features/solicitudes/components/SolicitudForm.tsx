import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, Send } from 'lucide-react';
import { useForm, useWatch } from 'react-hook-form';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
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
  const disponibilidadQuery = useQuery({ queryKey: ['disponibilidad-certificacion'], queryFn: solicitudesService.disponibilidad });
  const disponibilidad = disponibilidadQuery.data?.data;
  const puedeSolicitar = requiereSalario ? disponibilidad?.con_salario.puede_solicitar : disponibilidad?.sin_salario.puede_solicitar;
  const mutation = useMutation({
    mutationFn: solicitudesService.create,
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['disponibilidad-certificacion'] });
      navigate('/app/solicitudes/confirmacion', { replace: true, state: response.data });
    },
  });

  return (
    <form className="space-y-7 rounded-xl border border-border bg-white p-6 sm:p-8" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
      <div className="space-y-2 text-sm">
        <label htmlFor="contenido-certificacion" className="font-medium text-text">Contenido de la certificación</label>
        <select id="contenido-certificacion" aria-describedby="contenido-certificacion-ayuda" className="w-full rounded-lg border border-border px-3 py-2" {...register('tipo_certificado')}>
          <option value="laboral">Información laboral</option>
          <option value="funciones">Información laboral y funciones del Manual</option>
        </select>
        <p id="contenido-certificacion-ayuda" className="text-muted">Las funciones corresponden a la ficha del Manual asociada a su vinculación. El cupo mensual depende de la modalidad salarial.</p>
      </div>
      <fieldset>
        <legend className="text-sm font-semibold text-text">Modalidad seleccionada</legend>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          {[false, true].map((valor) => (
            <button className={`flex items-center justify-between rounded-lg border p-4 text-left transition ${requiereSalario === valor ? 'border-govBlue bg-blue-50' : 'border-border'}`} key={String(valor)} onClick={() => setValue('requiere_salario', valor)} type="button">
              <span><strong className="block text-text">{valor ? 'CON salario' : 'SIN salario'}</strong><small className="text-muted">{valor ? 'Incluye valor institucional' : 'Sin valores salariales'}</small></span>
              {requiereSalario === valor ? <Check className="text-govBlue" size={20} /> : null}
            </button>
          ))}
        </div>
      </fieldset>
      <label className="block space-y-2 text-sm">
        <span className="font-medium text-text">Observaciones (opcional)</span>
        <textarea className="min-h-24 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-govBlue focus:ring-2 focus:ring-govBlue/15" {...register('observaciones')} />
        {errors.observaciones ? <span className="text-villavoRed">{errors.observaciones.message}</span> : null}
      </label>
      <div className="rounded-lg bg-slate-50 p-4 text-sm leading-6 text-muted">
        Al confirmar, el sistema validará su cupo, cargo y fuentes institucionales. Si el pago está desactivado, generará el PDF inmediatamente.
      </div>
      {mutation.isError ? <p className="text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
      <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <Link to="/app/inicio"><Button icon={<ArrowLeft size={16} />} type="button" variant="secondary">Volver</Button></Link>
        <Button disabled={mutation.isPending || puedeSolicitar === false} icon={<Send size={16} />} type="submit">
          {mutation.isPending ? 'Generando…' : 'Confirmar y solicitar'}
        </Button>
      </div>
    </form>
  );
}
