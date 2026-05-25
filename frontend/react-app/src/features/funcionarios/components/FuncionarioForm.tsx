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
        <Input error={errors.nombres?.message} label="Nombres" {...register('nombres')} />
        <Input error={errors.apellidos?.message} label="Apellidos" {...register('apellidos')} />
        <Input error={errors.documento?.message} label="Documento" {...register('documento')} />
        <Input error={errors.email?.message} label="Correo" type="email" {...register('email')} />
        <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
        <Input error={errors.cargoId?.message} label="Cargo ID" {...register('cargoId')} />
        <div className="md:col-span-2">
          <Button type="submit">Guardar funcionario mock</Button>
        </div>
      </form>
    </Card>
  );
}
