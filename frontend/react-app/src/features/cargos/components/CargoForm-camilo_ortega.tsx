import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { cargoSchema, type CargoFormValues } from '../schemas/cargo.schema';
import { cargosService } from '../services/cargos.service';
import { getErrorMessage, getValidationErrors } from '../../../lib/utils/errors';
import { useState } from 'react';

export function CargoForm() {
  const queryClient = useQueryClient();
  const [success, setSuccess] = useState('');
  const { register, handleSubmit, reset, setError, formState: { errors } } = useForm<CargoFormValues>({
    resolver: zodResolver(cargoSchema),
    defaultValues: { estado: true },
  });
  const mutation = useMutation({
    mutationFn: cargosService.create,
    onSuccess: async (response) => {
      setSuccess(`Cargo ${response.data.denominacion} guardado correctamente.`);
      reset({ codigo: '', grado: '', denominacion: '', nivel: '', dependencia: '', estado: true });
      await queryClient.invalidateQueries({ queryKey: ['cargos'] });
    },
    onError: (error) => {
      Object.entries(getValidationErrors(error)).forEach(([field, messages]) => {
        setError(field as keyof CargoFormValues, { message: messages[0] });
      });
    },
  });

  return (
    <Card title="Cargo y grado" description="Formulario base para catalogo de cargos.">
      <form className="grid gap-4 md:grid-cols-3" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Input error={errors.codigo?.message} label="Codigo" {...register('codigo')} />
        <Input error={errors.denominacion?.message} label="Denominacion" {...register('denominacion')} />
        <Input error={errors.grado?.message} label="Grado" {...register('grado')} />
        <Input error={errors.nivel?.message} label="Nivel" {...register('nivel')} />
        <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
        <div className="md:col-span-3">
          {mutation.isError ? <p className="mb-2 text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
          {success ? <p className="mb-2 text-sm text-villavoGreen">{success}</p> : null}
          <Button disabled={mutation.isPending} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar cargo'}</Button>
        </div>
      </form>
    </Card>
  );
}
