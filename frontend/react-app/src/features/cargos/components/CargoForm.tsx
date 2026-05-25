import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { cargoSchema, type CargoFormValues } from '../schemas/cargo.schema';

export function CargoForm() {
  const { register, handleSubmit, formState: { errors } } = useForm<CargoFormValues>({
    resolver: zodResolver(cargoSchema),
  });

  return (
    <Card title="Cargo y grado" description="Formulario base para catalogo de cargos.">
      <form className="grid gap-4 md:grid-cols-3" onSubmit={handleSubmit(() => undefined)}>
        <Input error={errors.nombre?.message} label="Nombre" {...register('nombre')} />
        <Input error={errors.grado?.message} label="Grado" {...register('grado')} />
        <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
        <div className="md:col-span-3">
          <Button type="submit">Guardar cargo mock</Button>
        </div>
      </form>
    </Card>
  );
}
