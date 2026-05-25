import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { rangoSalarialSchema, type RangoSalarialFormValues } from '../schemas/rangoSalarial.schema';

export function RangoSalarialForm() {
  const { register, handleSubmit, formState: { errors } } = useForm<RangoSalarialFormValues>({
    resolver: zodResolver(rangoSalarialSchema),
  });

  return (
    <Card title="Rango salarial" description="Formulario mock para la tabla salarial por vigencia.">
      <form className="grid gap-4 md:grid-cols-4" onSubmit={handleSubmit(() => undefined)}>
        <Input error={errors.cargoId?.message} label="Cargo ID" {...register('cargoId')} />
        <Input error={errors.grado?.message} label="Grado" {...register('grado')} />
        <Input error={errors.salarioBase?.message} label="Salario base" type="number" {...register('salarioBase')} />
        <Input error={errors.vigenciaDesde?.message} label="Vigencia desde" type="date" {...register('vigenciaDesde')} />
        <div className="md:col-span-4">
          <Button type="submit">Guardar rango mock</Button>
        </div>
      </form>
    </Card>
  );
}
