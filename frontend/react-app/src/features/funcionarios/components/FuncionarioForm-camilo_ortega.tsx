import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { funcionarioSchema, type FuncionarioFormValues } from '../schemas/funcionario.schema';
import { funcionariosService } from '../services/funcionarios.service';
import { getErrorMessage, getValidationErrors } from '../../../lib/utils/errors';

export function FuncionarioForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { register, handleSubmit, reset, setError, formState: { errors } } = useForm<FuncionarioFormValues>({
    resolver: zodResolver(funcionarioSchema),
    defaultValues: { tipo_documento: 'CC', estado: 'activo' },
  });
  const funcionarioQuery = useQuery({
    queryKey: ['funcionario', id],
    queryFn: () => funcionariosService.getById(id ?? ''),
    enabled: Boolean(id),
  });

  useEffect(() => {
    const funcionario = funcionarioQuery.data?.data;
    if (!funcionario) return;
    reset({
      user_id: funcionario.user_id?.toString() ?? '',
      tipo_documento: funcionario.tipo_documento,
      numero_documento: funcionario.numero_documento,
      nombres: funcionario.nombres,
      apellidos: funcionario.apellidos,
      correo_institucional: funcionario.correo_institucional ?? '',
      telefono: funcionario.telefono ?? '',
      estado: funcionario.estado,
      fecha_ingreso: funcionario.fecha_ingreso ?? '',
      fecha_retiro: funcionario.fecha_retiro ?? '',
      dependencia: funcionario.dependencia ?? '',
      cargo_id: funcionario.cargo?.id.toString() ?? '',
    });
  }, [funcionarioQuery.data, reset]);

  const mutation = useMutation({
    mutationFn: (values: FuncionarioFormValues) => {
      const payload = {
        ...values,
        user_id: values.user_id ? Number(values.user_id) : null,
        cargo_id: values.cargo_id ? Number(values.cargo_id) : null,
        fecha_ingreso: values.fecha_ingreso || undefined,
        fecha_retiro: values.fecha_retiro || undefined,
      };
      return id ? funcionariosService.update(id, payload) : funcionariosService.create(payload);
    },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['funcionarios'] });
      navigate(`/admin/funcionarios/${response.data.id}`, { replace: true, state: { success: 'Funcionario guardado correctamente.' } });
    },
    onError: (error) => {
      Object.entries(getValidationErrors(error)).forEach(([field, messages]) => {
        setError(field as keyof FuncionarioFormValues, { message: messages[0] });
      });
    },
  });

  if (id && funcionarioQuery.isLoading) return <p className="text-sm text-muted">Cargando funcionario...</p>;

  return (
    <Card title="Datos del funcionario" description="Formulario base con validacion Zod listo para persistencia Laravel.">
      <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Input error={errors.user_id?.message} label="Usuario ID (opcional)" {...register('user_id')} />
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
          {mutation.isError ? <p className="mb-2 text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
          <Button disabled={mutation.isPending} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar funcionario'}</Button>
        </div>
      </form>
    </Card>
  );
}
