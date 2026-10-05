import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { getErrorMessage, getValidationErrors } from '../../../lib/utils/errors';
import { funcionarioSchema, type FuncionarioFormValues } from '../schemas/funcionario.schema';
import { funcionariosService } from '../services/funcionarios.service';
import { dependenciasService } from '../services/dependencias.service';
import { DependenciaSelect } from './DependenciaSelect';

export function FuncionarioForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [unidadSearch, setUnidadSearch] = useState('');
  const [cargoSearch, setCargoSearch] = useState('');
  const [seleccionModificada, setSeleccionModificada] = useState(false);
  const detail = useQuery({ queryKey: ['funcionario', id], queryFn: () => funcionariosService.getById(id ?? ''), enabled: Boolean(id), refetchOnWindowFocus: false });
  const { register, handleSubmit, reset, control, setValue, setError, formState: { errors } } = useForm<FuncionarioFormValues>({
    resolver: zodResolver(funcionarioSchema),
    defaultValues: { tipo_documento: 'CC', estado: 'activo', tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa', unidad_organizacional_id: '', cargo_id: '', fecha_ingreso: '' },
  });

  useEffect(() => {
    if (!detail.data) return;
    const item = detail.data.data;
    reset({
      tipo_documento: item.tipo_documento, numero_documento: item.numero_documento,
      nombres: item.nombres, apellidos: item.apellidos, estado: item.estado,
      cargo_id: String(item.cargo?.id ?? ''),
      correo_institucional: item.correo_institucional ?? '',
      telefono: item.telefono ?? '',
      unidad_organizacional_id: item.unidad_organizacional?.id ?? 'legacy',
      fecha_ingreso: item.fecha_ingreso ?? '',
      fecha_retiro: item.fecha_retiro ?? '',
      tipo_vinculacion: item.asignacion_actual?.tipo_vinculacion ?? 'planta',
      naturaleza_cargo: item.asignacion_actual?.naturaleza_cargo ?? 'carrera_administrativa',
    });
  }, [detail.data, reset]);

  const cargoId = useWatch({ control, name: 'cargo_id' });
  const unidadId = useWatch({ control, name: 'unidad_organizacional_id' });
  const ingreso = useWatch({ control, name: 'fecha_ingreso' });
  const fecha = id ? detail.data?.data.fecha_seleccion ?? '' : ingreso || new Date().toLocaleDateString('en-CA');
  const unidades = useQuery({ queryKey: ['dependencias', fecha], enabled: Boolean(fecha), queryFn: () => dependenciasService.list(fecha, '') });
  const cargos = useQuery({ queryKey: ['cargos-dependencia', unidadId, fecha, cargoSearch], enabled: Boolean(unidadId && unidadId !== 'legacy' && fecha), queryFn: () => dependenciasService.cargos(unidadId, fecha, cargoSearch) });
  const conservar = Boolean(id && !seleccionModificada);
  const perfil = useQuery({ queryKey: ['perfil-dependencia', unidadId, cargoId, fecha], enabled: Boolean(!conservar && unidadId && cargoId && fecha), retry: false, queryFn: () => dependenciasService.resolver(unidadId, cargoId, fecha) });
  const opcionesUnidades = (unidades.data ?? []).filter((item) => item.id === unidadId || item.nombre.toLocaleLowerCase().includes(unidadSearch.toLocaleLowerCase())).map((item) => ({ id: item.id, label: item.nombre }));
  if (conservar && !opcionesUnidades.some((item) => item.id === unidadId)) opcionesUnidades.unshift({ id: unidadId, label: detail.data?.data.unidad_organizacional?.nombre ?? detail.data?.data.dependencia ?? 'Dependencia histórica sin mapear' });
  const opcionesCargos = (cargos.data ?? []).map((item) => ({ id: String(item.id), label: `${item.denominacion} · ${item.codigo}/${item.grado}${item.perfiles_count > 1 ? ' · Requiere revisión de perfiles' : ''}` }));
  const actual = detail.data?.data.cargo;
  if (conservar && actual && !opcionesCargos.some((item) => item.id === cargoId)) opcionesCargos.unshift({ id: cargoId, label: `${actual.denominacion} · ${actual.codigo}/${actual.grado} (vinculación registrada)` });

  const mutation = useMutation({
    mutationFn: (values: FuncionarioFormValues) => id ? funcionariosService.update(id, values, conservar) : funcionariosService.create(values),
    onSuccess: (response) => navigate(`/admin/funcionarios/${response.data.id}`, { replace: true }),
    onError: (error) => {
      for (const [field, messages] of Object.entries(getValidationErrors(error))) {
        if (field in funcionarioSchema.shape) setError(field as keyof FuncionarioFormValues, { message: messages[0] });
      }
    },
  });

  if (id && detail.isPending) return <p role="status">Cargando funcionario...</p>;
  if (id && detail.isError) return <p role="alert">No se pudo cargar el funcionario. <button type="button" onClick={() => void detail.refetch()}>Reintentar</button></p>;

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
        <DependenciaSelect label="Dependencia" value={unidadId ?? ''} search={unidadSearch} onSearch={setUnidadSearch} options={opcionesUnidades}
          onChange={(value) => { setSeleccionModificada(true); setValue('unidad_organizacional_id', value, { shouldValidate: true }); setValue('cargo_id', ''); setCargoSearch(''); }}
          placeholder="Seleccione una dependencia..." empty="No se encontraron dependencias." loading={unidades.isFetching}
          error={unidades.isError ? 'No se pudieron cargar las dependencias.' : errors.unidad_organizacional_id?.message} retry={() => void unidades.refetch()} />
        <DependenciaSelect label="Cargo" value={cargoId ?? ''} search={cargoSearch} onSearch={(value) => { setCargoSearch(value); setValue('cargo_id', ''); setSeleccionModificada(true); }} options={opcionesCargos}
          onChange={(value) => { setSeleccionModificada(true); setValue('cargo_id', value, { shouldValidate: true }); }}
          placeholder={unidadId ? 'Seleccione un cargo...' : 'Seleccione primero una dependencia...'} empty="No hay cargos asociados a esta dependencia."
          disabled={!unidadId || unidadId === 'legacy'} loading={cargos.isFetching}
          error={cargos.isError ? 'No se pudieron cargar los cargos.' : errors.cargo_id?.message} retry={() => void cargos.refetch()} />
        {!ingreso && !id && <p className="text-sm text-muted md:col-span-2">Indique la fecha de vinculación para consultar dependencias y cargos aplicables.</p>}
        {detail.data?.data.vinculacion_legacy && conservar && <p className="text-sm text-muted md:col-span-2">Vinculación histórica conservada. La dependencia se muestra desde el perfil registrado, cuando existe. Texto histórico: {detail.data.data.dependencia || 'Sin dependencia informada'}. Para cambiarla, seleccione una dependencia y un cargo aplicables; el historial anterior se mantiene.</p>}
        {conservar && detail.data?.data.asignacion_actual?.ficha_manual && <p className="text-sm text-muted md:col-span-2">Perfil funcional registrado: {detail.data.data.asignacion_actual.ficha_manual.source_id ?? 'Perfil normativo asociado'}.</p>}
        {!conservar && perfil.isFetching && <p className="text-sm text-muted md:col-span-2" role="status">Resolviendo perfil funcional...</p>}
        {!conservar && cargoId && perfil.isError && <p className="text-sm text-error md:col-span-2" role="alert">{Object.values(getValidationErrors(perfil.error))[0]?.[0] ?? getErrorMessage(perfil.error)} <button type="button" className="underline" onClick={() => void perfil.refetch()}>Reintentar resolución</button></p>}
        {!conservar && cargoId && perfil.data && <p className="text-sm text-muted md:col-span-2">Perfil funcional asociado: {perfil.data.source_id ?? 'Perfil normativo'} · {perfil.data.funciones_count} funciones</p>}
        <Input error={errors.fecha_ingreso?.message} label="Fecha de vinculación" type="date" {...register('fecha_ingreso', { onChange: () => { if (!id) { setValue('cargo_id', ''); setValue('unidad_organizacional_id', ''); } } })} />
        <Input error={errors.fecha_retiro?.message} label="Fecha de retiro (opcional)" type="date" {...register('fecha_retiro')} />
        <Select error={errors.tipo_vinculacion?.message} label="Tipo de vinculación" {...register('tipo_vinculacion')}><option value="planta">Planta</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option><option value="temporal">Temporal</option></Select>
        <Select error={errors.naturaleza_cargo?.message} label="Naturaleza del cargo" {...register('naturaleza_cargo')}><option value="carrera_administrativa">Carrera administrativa</option><option value="libre_nombramiento">Libre nombramiento</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option></Select>
      </FormSection>

      <FormSection description="La cuenta se crea automáticamente. No se muestra ni almacena una contraseña en este formulario." title="Acceso">
        <Select error={errors.estado?.message} label="Estado del funcionario" {...register('estado')}><option value="activo">Activo</option><option value="suspendido">Suspendido</option><option value="retirado">Retirado</option></Select>
        <div className="rounded-lg border border-info/20 bg-info/5 p-4 text-sm leading-6 text-muted"><strong className="block text-text">Acceso temporal</strong>En el alta, la cédula funciona como credencial temporal y el primer ingreso exige cambio de contraseña.</div>
      </FormSection>

      {mutation.isError ? <p className="rounded-md border border-error/20 bg-error/5 p-4 text-sm text-error" role="alert">{getErrorMessage(mutation.error)}</p> : null}
      <div className="flex justify-end border-t border-border pt-5"><Button disabled={mutation.isPending || (!conservar && (!perfil.data || perfil.isFetching || perfil.isError || !cargoId || !unidadId))} icon={<Save size={17} />} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar funcionario'}</Button></div>
    </form>
  );
}

function FormSection({ title, description, children }: { title: string; description: string; children: ReactNode }) {
  return <section className="surface-section p-5 sm:p-6"><div className="mb-5 border-b border-border pb-4"><h2 className="text-lg font-bold text-text">{title}</h2><p className="mt-1 text-sm text-muted">{description}</p></div><div className="grid gap-5 md:grid-cols-2">{children}</div></section>;
}
