import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { rangoSalarialSchema, type RangoSalarialFormValues } from '../schemas/rangoSalarial.schema';
import { rangosSalarialesService } from '../services/rangosSalariales.service';
import { getErrorMessage, getValidationErrors } from '../../../lib/utils/errors';

export function RangoSalarialForm() {
  const queryClient = useQueryClient();
  const [success, setSuccess] = useState('');
  const { register, handleSubmit, reset, setError, formState: { errors } } = useForm<RangoSalarialFormValues>({
    resolver: zodResolver(rangoSalarialSchema),
    defaultValues: { moneda: 'COP', estado: true, vigencia_anio: new Date().getFullYear() },
  });
  const mutation = useMutation({
    mutationFn: rangosSalarialesService.create,
    onSuccess: async (response) => {
      setSuccess(`Rango ${response.data.codigo}-${response.data.grado} guardado correctamente.`);
      reset({ codigo: '', grado: '', moneda: 'COP', vigencia_anio: new Date().getFullYear(), salario_basico: 0, observaciones: '', estado: true });
      await queryClient.invalidateQueries({ queryKey: ['rangos-salariales'] });
    },
    onError: (error) => {
      Object.entries(getValidationErrors(error)).forEach(([field, messages]) => {
        setError(field as keyof RangoSalarialFormValues, { message: messages[0] });
      });
    },
  });

  return (
    <Card title="Rango salarial" description="Formulario para registrar la tabla salarial por vigencia.">
      <form className="grid gap-4 md:grid-cols-4" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Input error={errors.codigo?.message} label="Código" {...register('codigo')} />
        <Input error={errors.grado?.message} label="Grado" {...register('grado')} />
        <Input error={errors.vigencia_anio?.message} label="Vigencia" type="number" {...register('vigencia_anio')} />
        <Input error={errors.salario_basico?.message} label="Salario básico" type="number" {...register('salario_basico')} />
        <Input error={errors.moneda?.message} label="Moneda" {...register('moneda')} />
        <Input error={errors.observaciones?.message} label="Observaciones" {...register('observaciones')} />
        <div className="md:col-span-4">
          {mutation.isError ? <p className="mb-2 text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
          {success ? <p className="mb-2 text-sm text-villavoGreen">{success}</p> : null}
          <Button disabled={mutation.isPending} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar rango'}</Button>
        </div>
      </form>
    </Card>
  );
}
