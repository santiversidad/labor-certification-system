import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { funcionarioSchema, type FuncionarioFormValues } from '../schemas/funcionario.schema';

export function FuncionarioForm() {
  const { register, handleSubmit, formState: { errors } } = useForm<FuncionarioFormValues>({
    resolver: zodResolver(funcionarioSchema),
  });

  return (
    <Card title="Datos del funcionario" description="Formulario base con validacion Zod listo para persistencia Laravel.">
      <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit(() => undefined)}>
        <Input error={errors.tipo_documento?.message} label="Tipo documento" {...register('tipo_documento')} />
        <Input error={errors.numero_documento?.message} label="Numero documento" {...register('numero_documento')} />
        <Input error={errors.nombres?.message} label="Nombres" {...register('nombres')} />
        <Input error={errors.apellidos?.message} label="Apellidos" {...register('apellidos')} />
        <Input error={errors.correo_institucional?.message} label="Correo institucional" type="email" {...register('correo_institucional')} />
        <Input error={errors.telefono?.message} label="Telefono" {...register('telefono')} />
        <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
        <Input error={errors.cargo_id?.message} label="Cargo ID" {...register('cargo_id')} />
        <Input error={errors.fecha_ingreso?.message} label="Fecha ingreso" type="date" {...register('fecha_ingreso')} />
        <Input error={errors.fecha_retiro?.message} label="Fecha retiro" type="date" {...register('fecha_retiro')} />
        <div className="md:col-span-2">
          <Button type="submit">Guardar funcionario</Button>
        </div>
      </form>
    </Card>
  );
}
