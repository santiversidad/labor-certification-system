import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
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
import { manualSelectionError } from '../services/manualSelectionError';

export function FuncionarioForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [unidadSearch, setUnidadSearch] = useState('');
  const [cargoSearch, setCargoSearch] = useState('');
  const [seleccionModificada, setSeleccionModificada] = useState(false);
  const [mostrarFunciones, setMostrarFunciones] = useState(false);
  const unidadRef = useRef<HTMLSelectElement>(null);
  const cargoRef = useRef<HTMLSelectElement>(null);
  const problemaId = useId();
  const detail = useQuery({ queryKey: ['funcionario', id], queryFn: () => funcionariosService.getById(id ?? ''), enabled: Boolean(id), refetchOnWindowFocus: false });
  const { register, handleSubmit, reset, control, setValue, setError, clearErrors, formState: { errors } } = useForm<FuncionarioFormValues>({
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
      manual_cargo_version_id: item.asignacion_actual?.manual_cargo_version_id ? String(item.asignacion_actual.manual_cargo_version_id) : '',
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
  const fichaId = useWatch({ control, name: 'manual_cargo_version_id' });
  const opcionId = fichaId ? `ficha-${fichaId}` : cargoId;
  const unidadId = useWatch({ control, name: 'unidad_organizacional_id' });
  const ingreso = useWatch({ control, name: 'fecha_ingreso' });
  const fecha = id ? detail.data?.data.fecha_seleccion ?? '' : ingreso || new Date().toLocaleDateString('en-CA');
  const unidades = useQuery({ queryKey: ['dependencias', fecha], enabled: Boolean(fecha), retry: false, queryFn: () => dependenciasService.list(fecha, '') });
  const cargos = useQuery({ queryKey: ['cargos-dependencia', unidadId, fecha, cargoSearch], enabled: Boolean(unidadId && unidadId !== 'legacy' && fecha), retry: false, queryFn: () => dependenciasService.cargos(unidadId, fecha, cargoSearch) });
  const conservar = Boolean(id && !seleccionModificada);
  const perfil = useQuery({ queryKey: ['perfil-dependencia', unidadId, cargoId, fichaId, fecha], enabled: Boolean(!conservar && unidadId && cargoId && fecha), retry: false, refetchOnWindowFocus: false, queryFn: () => fichaId ? dependenciasService.resolver(unidadId, cargoId, fecha, fichaId) : dependenciasService.resolver(unidadId, cargoId, fecha) });
  const funciones = useQuery({ queryKey: ['funciones-perfil', id, unidadId, cargoId, fichaId, fecha, conservar], enabled: Boolean(mostrarFunciones && fichaId && (conservar || perfil.data)), retry: false, refetchOnWindowFocus: false, queryFn: () => conservar ? dependenciasService.funcionesRegistradas(id!, fichaId!) : dependenciasService.funciones(unidadId, cargoId, fecha, fichaId!) });
  const opcionesUnidades = (unidades.data ?? []).filter((item) => item.id === unidadId || item.nombre.toLocaleLowerCase().includes(unidadSearch.toLocaleLowerCase())).map((item) => ({ id: item.id, label: item.nombre }));
  if (conservar && !opcionesUnidades.some((item) => item.id === unidadId)) opcionesUnidades.unshift({ id: unidadId, label: detail.data?.data.unidad_organizacional?.nombre ?? detail.data?.data.dependencia ?? 'Dependencia histórica sin mapear' });
  const opcionesCargos = (cargos.data ?? []).map((item) => ({ id: item.manual_cargo_version_id ? `ficha-${item.manual_cargo_version_id}` : String(item.id), label: `${item.denominacion} · ${item.codigo}/${item.grado}${item.manual_cargo_version_id ? ` · ${item.pagina_verificada ? `Página ${item.pagina_inicio}` : 'Página pendiente de verificación'}${item.transversal ? ' · Transversal' : ''}${item.seleccionable === false ? ' · Requiere revisión del Manual' : ''}` : item.perfiles_count > 1 ? ' · Requiere revisión del Manual' : ''}` }));
  const actual = detail.data?.data.cargo;
  if (conservar && actual && !opcionesCargos.some((item) => item.id === opcionId)) opcionesCargos.unshift({ id: opcionId, label: `${actual.denominacion} · ${actual.codigo}/${actual.grado}${detail.data?.data.asignacion_actual?.ficha_manual?.pagina_inicio ? ` · Página ${detail.data.data.asignacion_actual.ficha_manual.pagina_inicio}` : ''} (vinculación registrada)` });

  const mutation = useMutation({
    mutationFn: (values: FuncionarioFormValues) => id ? funcionariosService.update(id, values, conservar) : funcionariosService.create(values),
    onSuccess: (response) => navigate(`/admin/funcionarios/${response.data.id}`, { replace: true }),
    onError: (error) => {
      if (manualSelectionError(error).domain) return;
      for (const [field, messages] of Object.entries(getValidationErrors(error))) {
        if (field in funcionarioSchema.shape) setError(field as keyof FuncionarioFormValues, { message: messages[0] });
      }
    },
  });

  const problema = !conservar && cargoId && (perfil.isError || (mutation.isError && manualSelectionError(mutation.error).domain))
    ? manualSelectionError(perfil.isError ? perfil.error : mutation.error) : null;
  const seleccionBloqueada = !conservar && (!perfil.data || perfil.isFetching || perfil.isError || !cargoId || !unidadId || Boolean(problema));
  function limpiarSeleccion(cambiarUnidad = false) {
    setSeleccionModificada(true);
    setValue('cargo_id', '');
    setValue('manual_cargo_version_id', '');
    setMostrarFunciones(false);
    clearErrors('cargo_id');
    setCargoSearch('');
    mutation.reset();
    if (cambiarUnidad) {
      setValue('unidad_organizacional_id', '');
      clearErrors('unidad_organizacional_id');
      setUnidadSearch('');
      unidadRef.current?.focus();
    } else {
      cargoRef.current?.focus();
    }
  }

  if (id && detail.isPending) return <p role="status">Cargando funcionario...</p>;
  if (id && detail.isError) return <p role="alert">No se pudo cargar el funcionario. <button type="button" onClick={() => void detail.refetch()}>Reintentar</button></p>;

  return (
    <form className="space-y-6" noValidate onSubmit={handleSubmit((values) => { if (!seleccionBloqueada && !mutation.isPending) mutation.mutate(values); })}>
      <FormSection description="Identificación y datos de contacto institucionales." title="Datos personales">
        <Select error={errors.tipo_documento?.message} label="Tipo de documento" {...register('tipo_documento')}><option>CC</option><option>CE</option><option>PA</option><option>TI</option></Select>
        <Input error={errors.numero_documento?.message} label="Número de cédula" {...register('numero_documento')} />
        <Input error={errors.nombres?.message} label="Nombres" {...register('nombres')} />
        <Input error={errors.apellidos?.message} label="Apellidos" {...register('apellidos')} />
        <Input error={errors.correo_institucional?.message} label="Correo institucional" type="email" {...register('correo_institucional')} />
        <Input error={errors.telefono?.message} label="Teléfono" {...register('telefono')} />
      </FormSection>

      <FormSection description="Condiciones de la relación laboral que se certificará." title="Vinculación">
        <DependenciaSelect selectRef={unidadRef} label="Dependencia" value={unidadId ?? ''} search={unidadSearch} onSearch={setUnidadSearch} options={opcionesUnidades}
          onChange={(value) => { setMostrarFunciones(false); setValue('manual_cargo_version_id', ''); mutation.reset(); setSeleccionModificada(true); setValue('unidad_organizacional_id', value, { shouldValidate: true }); setValue('cargo_id', ''); setCargoSearch(''); }}
          placeholder="Seleccione una dependencia..." empty="No se encontraron dependencias." loading={unidades.isFetching}
          error={unidades.isError ? 'No se pudieron cargar las dependencias.' : errors.unidad_organizacional_id?.message} retry={unidades.isError && manualSelectionError(unidades.error).retryable ? () => void unidades.refetch() : undefined} />
        <DependenciaSelect selectRef={cargoRef} describedBy={problema ? problemaId : undefined} label="Cargo" value={opcionId ?? ''} search={cargoSearch} onSearch={(value) => { setMostrarFunciones(false); setValue('manual_cargo_version_id', ''); mutation.reset(); setCargoSearch(value); setValue('cargo_id', ''); setSeleccionModificada(true); }} options={opcionesCargos}
          onChange={(value) => { setMostrarFunciones(false); mutation.reset(); setSeleccionModificada(true); const selected = (cargos.data ?? []).find((item) => (item.manual_cargo_version_id ? `ficha-${item.manual_cargo_version_id}` : String(item.id)) === value); setValue('manual_cargo_version_id', selected?.manual_cargo_version_id ? String(selected.manual_cargo_version_id) : ''); setValue('cargo_id', selected ? String(selected.id) : '', { shouldValidate: true }); }}
          placeholder={unidadId ? 'Seleccione un cargo...' : 'Seleccione primero una dependencia...'} empty="No hay cargos asociados a esta dependencia."
          disabled={!unidadId || unidadId === 'legacy'} loading={cargos.isFetching}
          error={cargos.isError ? 'No se pudieron cargar los cargos.' : errors.cargo_id?.message} retry={cargos.isError && manualSelectionError(cargos.error).retryable ? () => void cargos.refetch() : undefined} />
        {!ingreso && !id && <p className="text-sm text-muted md:col-span-2">Indique la fecha de vinculación para consultar dependencias y cargos aplicables.</p>}
        {detail.data?.data.vinculacion_legacy && conservar && <p className="text-sm text-muted md:col-span-2">Vinculación histórica conservada. La dependencia se muestra desde el perfil registrado, cuando existe. Texto histórico: {detail.data.data.dependencia || 'Sin dependencia informada'}. Para cambiarla, seleccione una dependencia y un cargo aplicables; el historial anterior se mantiene.</p>}
        {conservar && detail.data?.data.asignacion_actual?.ficha_manual && <p className="text-sm text-muted md:col-span-2">Perfil funcional registrado: {detail.data.data.asignacion_actual.ficha_manual.source_id ?? 'Perfil normativo asociado'}{detail.data.data.asignacion_actual.ficha_manual.pagina_inicio ? ` · Página ${detail.data.data.asignacion_actual.ficha_manual.pagina_inicio}` : ''}.</p>}
        {!conservar && perfil.isFetching && <p className="text-sm text-muted md:col-span-2" role="status">Resolviendo perfil funcional...</p>}
        {!conservar && cargoId && (cargos.data ?? []).filter((item) => String(item.id) === cargoId).length > 1 && <p className="text-sm text-muted md:col-span-2">Hay varios perfiles similares. Verifique la página y las funciones de la ficha seleccionada antes de guardar.</p>}
        {problema && <div id={problemaId} className="rounded-lg border border-info/30 bg-info/5 p-4 text-sm leading-6 md:col-span-2" role="alert">
          <strong className="block text-text">{problema.title}</strong>
          <p className="mt-1 text-muted">{problema.description}</p>
          {!problema.retryable && <p className="mt-2 text-muted">Seleccione otro cargo o revise esta situación con la Dirección de Personal antes de registrar la vinculación.</p>}
          <p className="mt-2 font-semibold text-text">No se puede guardar la vinculación hasta validar un perfil único del Manual.</p>
          <div className="mt-3 flex flex-wrap gap-3">
            {problema.retryable ? <Button type="button" onClick={() => { mutation.reset(); void perfil.refetch(); }}>Reintentar</Button> : <>
              <Button type="button" onClick={() => limpiarSeleccion()}>Elegir otro cargo</Button>
              <Button type="button" variant="secondary" onClick={() => limpiarSeleccion(true)}>Cambiar dependencia</Button>
            </>}
          </div>
        </div>}
        {!conservar && cargoId && perfil.data && !perfil.isError && !problema && <p className="text-sm text-muted md:col-span-2">Perfil funcional asociado: {perfil.data.source_id ?? 'Perfil normativo'} · {perfil.data.funciones_count} funciones{perfil.data.pagina_inicio ? ` · Página ${perfil.data.pagina_inicio}` : ''}</p>}
        {fichaId && (conservar || (perfil.data && !problema)) && <div className="md:col-span-2">
          <Button type="button" variant="secondary" aria-expanded={mostrarFunciones} onClick={() => setMostrarFunciones(!mostrarFunciones)}>Ver funciones del cargo</Button>
          {mostrarFunciones && <section aria-label="Funciones del cargo" className="mt-4 space-y-4 rounded-lg border border-border p-4 sm:p-5">
            {funciones.isFetching && <p role="status">Cargando funciones...</p>}
            {funciones.isError && <p role="alert">No se pudieron consultar las funciones de esta ficha.{manualSelectionError(funciones.error).retryable && <button type="button" className="ml-2 underline" onClick={() => void funciones.refetch()}>Reintentar</button>}</p>}
            {funciones.data && !funciones.isFetching && !funciones.isError && <>
              <p className="font-semibold">{funciones.data.denominacion} · Código {funciones.data.codigo} · Grado {funciones.data.grado}</p>
              <p>{detail.data?.data.unidad_organizacional?.nombre && conservar ? detail.data.data.unidad_organizacional.nombre : opcionesUnidades.find((u) => u.id === unidadId)?.label} · {funciones.data.area_funcional}</p>
              <p>{funciones.data.source_id} · {funciones.data.pagina_verificada ? `Página ${funciones.data.pagina_inicio}` : 'Página pendiente de verificación'} · {funciones.data.version}</p>
              <ol className="list-decimal space-y-3 pl-6 leading-7">{funciones.data.funciones.map((funcion) => <li key={funcion.orden} className="whitespace-pre-wrap break-words">{funcion.grupo && <p className="font-semibold">{funcion.grupo}</p>}{funcion.descripcion}</li>)}</ol>
              {!funciones.data.funciones.length && <p>Esta ficha no contiene funciones verificables. Requiere revisión administrativa.</p>}
            </>}
          </section>}
        </div>}
        <Input error={errors.fecha_ingreso?.message} label="Fecha de vinculación" type="date" {...register('fecha_ingreso', { onChange: () => { if (!id) { setMostrarFunciones(false); setValue('manual_cargo_version_id', ''); setValue('cargo_id', ''); setValue('unidad_organizacional_id', ''); } } })} />
        <Input error={errors.fecha_retiro?.message} label="Fecha de retiro (opcional)" type="date" {...register('fecha_retiro')} />
        <Select error={errors.tipo_vinculacion?.message} label="Tipo de vinculación" {...register('tipo_vinculacion')}><option value="planta">Planta</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option><option value="temporal">Temporal</option></Select>
        <Select error={errors.naturaleza_cargo?.message} label="Naturaleza del cargo" {...register('naturaleza_cargo')}><option value="carrera_administrativa">Carrera administrativa</option><option value="libre_nombramiento">Libre nombramiento</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option></Select>
      </FormSection>

      <FormSection description="La cuenta se crea automáticamente. No se muestra ni almacena una contraseña en este formulario." title="Acceso">
        <Select error={errors.estado?.message} label="Estado del funcionario" {...register('estado')}><option value="activo">Activo</option><option value="suspendido">Suspendido</option><option value="retirado">Retirado</option></Select>
        <div className="rounded-lg border border-info/20 bg-info/5 p-4 text-sm leading-6 text-muted"><strong className="block text-text">Acceso temporal</strong>En el alta, la cédula funciona como credencial temporal y el primer ingreso exige cambio de contraseña.</div>
      </FormSection>

      {mutation.isError && !problema ? <p className="rounded-md border border-error/20 bg-error/5 p-4 text-sm text-error" role="alert">{getErrorMessage(mutation.error)}</p> : null}
      <div className="flex justify-end border-t border-border pt-5"><Button aria-describedby={problema ? problemaId : undefined} disabled={mutation.isPending || seleccionBloqueada} icon={<Save size={17} />} type="submit">{mutation.isPending ? 'Guardando...' : 'Guardar funcionario'}</Button></div>
    </form>
  );
}

function FormSection({ title, description, children }: { title: string; description: string; children: ReactNode }) {
  return <section className="surface-section p-5 sm:p-6"><div className="mb-5 border-b border-border pb-4"><h2 className="text-lg font-bold text-text">{title}</h2><p className="mt-1 text-sm text-muted">{description}</p></div><div className="grid gap-5 md:grid-cols-2">{children}</div></section>;
}
