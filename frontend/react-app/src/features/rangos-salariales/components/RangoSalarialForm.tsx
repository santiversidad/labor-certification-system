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
    <Card title="Rango salarial" description="Formulario para la tabla salarial por vigencia.">
      <form className="grid gap-4 md:grid-cols-4" onSubmit={handleSubmit(() => undefined)}>
        <Input error={errors.codigo?.message} label="Codigo" {...register('codigo')} />
        <Input error={errors.grado?.message} label="Grado" {...register('grado')} />
        <Input error={errors.vigencia_anio?.message} label="Vigencia" type="number" {...register('vigencia_anio')} />
        <Input error={errors.salario_basico?.message} label="Salario basico" type="number" {...register('salario_basico')} />
        <Input error={errors.moneda?.message} label="Moneda" {...register('moneda')} />
        <Input error={errors.observaciones?.message} label="Observaciones" {...register('observaciones')} />
        <div className="md:col-span-4">
          <Button type="submit">Guardar rango</Button>
        </div>
      </form>
    </Card>
  );
}
