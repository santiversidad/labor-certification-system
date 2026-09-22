import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';
import { getErrorMessage } from '../../../lib/utils/errors';
import { cargosService } from '../../cargos/services/cargos.service';
import { funcionarioSchema, type FuncionarioFormValues } from '../schemas/funcionario.schema';
import { funcionariosService } from '../services/funcionarios.service';
import { FichaManualSelect, type FichaManual } from './FichaManualSelect';

export function FuncionarioForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const cargos = useQuery({ queryKey: ['cargos-opciones'], queryFn: cargosService.options });
  const detail = useQuery({ queryKey: ['funcionario', id], queryFn: () => funcionariosService.getById(id ?? ''), enabled: Boolean(id) });
  const { register, handleSubmit, reset, control, setValue, formState: { errors } } = useForm<FuncionarioFormValues>({
    resolver: zodResolver(funcionarioSchema),
    defaultValues: { tipo_documento: 'CC', estado: 'activo', tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa', manual_cargo_version_id: '' },
  });

  useEffect(() => {
    if (!detail.data) return;
    const item = detail.data.data;
    reset({
      ...item,
      cargo_id: String(item.cargo?.id ?? ''),
      manual_cargo_version_id: String(item.asignacion_actual?.manual_cargo_version_id ?? ''),
      correo_institucional: item.correo_institucional ?? '',
      telefono: item.telefono ?? '',
      dependencia: item.dependencia ?? '',
      fecha_ingreso: item.fecha_ingreso ?? '',
      fecha_retiro: item.fecha_retiro ?? '',
      tipo_vinculacion: item.asignacion_actual?.tipo_vinculacion ?? 'planta',
      naturaleza_cargo: item.asignacion_actual?.naturaleza_cargo ?? 'carrera_administrativa',
    });
  }, [detail.data, reset]);

  const cargoId = useWatch({ control, name: 'cargo_id' });
  const fichaId = useWatch({ control, name: 'manual_cargo_version_id' });
  const fichas = useQuery({ queryKey: ['manual-fichas', cargoId], enabled: Boolean(cargoId), queryFn: async () => (await apiClient.get<ApiResponse<FichaManual[]>>('/manual-funciones/fichas', { params: { cargo_id: cargoId } })).data.data });
  useEffect(() => {
    if (!fichas.data) return;
    if (fichaId && fichas.data.some((ficha) => String(ficha.id) === fichaId)) return;
    const validas = fichas.data.filter((ficha) => ficha.vigente);
    setValue('manual_cargo_version_id', validas.length === 1 ? String(validas[0].id) : '');
  }, [fichas.data, fichaId, setValue]);

  const mutation = useMutation({
    mutationFn: (values: FuncionarioFormValues) => id ? funcionariosService.update(id, values) : funcionariosService.create(values),
    onSuccess: (response) => navigate(`/admin/funcionarios/${response.data.id}`, { replace: true }),
  });

  return (
    <form className="space-y-6" noValidate onSubmit={handleSubmit((values) => mutation.mutate(values))}>
      <FormSection description="Identificación y datos de contacto institucionales." title="Datos personales">
        <Select error={errors.tipo_documento?.message} label="Tipo de documento" {...register('tipo_documento')}><option>CC</option><option>CE</option><option>PA</option><option>TI</option></Select>
        <Input error={errors.numero_documento?.message} label="Número de cédula" {...register('numero_documento')} />
        <Input error={errors.nombres?.message} label="Nombres" {...register('nombres')} />
        <Input error={errors.apellidos?.message} label="Apellidos" {...register('apellidos')} />
        <Input error={errors.correo_institucional?.message} label="Correo institucional" type="email" {...register('correo_institucional')} />
        <Input error={errors.telefono?.message} label="Teléfono" {...register('telefono')} />
      </FormSection>

      <FormSection description="Condiciones de la relación laboral que se certificará." title="Vinculación">
        <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
        <Input error={errors.fecha_ingreso?.message} label="Fecha de vinculación" type="date" {...register('fecha_ingreso')} />
        <Input error={errors.fecha_retiro?.message} label="Fecha de retiro (opcional)" type="date" {...register('fecha_retiro')} />
        <Select error={errors.tipo_vinculacion?.message} label="Tipo de vinculación" {...register('tipo_vinculacion')}><option value="planta">Planta</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option><option value="temporal">Temporal</option></Select>
        <Select error={errors.naturaleza_cargo?.message} label="Naturaleza del cargo" {...register('naturaleza_cargo')}><option value="carrera_administrativa">Carrera administrativa</option><option value="libre_nombramiento">Libre nombramiento</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option></Select>
      </FormSection>

      <FormSection description="El cargo determina las fichas normativas compatibles." title="Cargo">
        <div className="md:col-span-2"><Select error={errors.cargo_id?.message} label="Cargo" {...register('cargo_id', { onChange: () => setValue('manual_cargo_version_id', '') })}><option value="">Seleccione...</option>{cargos.data?.data.map((item) => <option key={item.id} value={item.id}>{item.denominacion} · {item.codigo}/{item.grado}</option>)}</Select></div>
      </FormSection>

      <FormSection description="Seleccione explícitamente la ficha vigente que corresponde a la dependencia y área." title="Ficha del Manual">
        <div className="md:col-span-2"><FichaManualSelect error={fichas.isError ? 'No se pudieron cargar las fichas.' : errors.manual_cargo_version_id?.message} fichas={fichas.data ?? []} loading={fichas.isFetching} onChange={(value) => setValue('manual_cargo_version_id', value, { shouldValidate: true })} value={fichaId ?? ''} /></div>
      </FormSection>

      <FormSection description="La cuenta se crea automáticamente. No se muestra ni almacena una contraseña en este formulario." title="Acceso">
        <Select error={errors.estado?.message} label="Estado del funcionario" {...register('estado')}><option value="activo">Activo</option><option value="suspendido">Suspendido</option><option value="retirado">Retirado</option></Select>
        <div className="rounded-lg border border-info/20 bg-info/5 p-4 text-sm leading-6 text-muted"><strong className="block text-text">Acceso temporal</strong>En el alta, la cédula funciona como credencial temporal y el primer ingreso exige cambio de contraseña.</div>
      </FormSection>

      {mutation.isError ? <p className="rounded-md border border-error/20 bg-error/5 p-4 text-sm text-error" role="alert">{getErrorMessage(mutation.error)}</p> : null}
      <div className="flex justify-end border-t border-border pt-5"><Button disabled={mutation.isPending || fichas.isFetching || fichas.isError || !fichas.data?.some((ficha) => String(ficha.id) === fichaId)} icon={<Save size={17} />} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar funcionario'}</Button></div>
    </form>
  );
}

function FormSection({ title, description, children }: { title: string; description: string; children: ReactNode }) {
  return <section className="surface-section p-5 sm:p-6"><div className="mb-5 border-b border-border pb-4"><h2 className="text-lg font-bold text-text">{title}</h2><p className="mt-1 text-sm text-muted">{description}</p></div><div className="grid gap-5 md:grid-cols-2">{children}</div></section>;
}
