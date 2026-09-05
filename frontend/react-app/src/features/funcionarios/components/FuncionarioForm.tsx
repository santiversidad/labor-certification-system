import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import { apiClient } from '../../../lib/api/apiClient';
import type { ApiResponse } from '../../../lib/api/api.types';
import { FichaManualSelect, type FichaManual } from './FichaManualSelect';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { getErrorMessage } from '../../../lib/utils/errors';
import { cargosService } from '../../cargos/services/cargos.service';
import { funcionarioSchema, type FuncionarioFormValues } from '../schemas/funcionario.schema';
import { funcionariosService } from '../services/funcionarios.service';

export function FuncionarioForm() {
  const { id } = useParams(); const navigate = useNavigate();
  const cargos = useQuery({ queryKey: ['cargos-opciones'], queryFn: cargosService.options });
  const detail = useQuery({ queryKey: ['funcionario', id], queryFn: () => funcionariosService.getById(id ?? ''), enabled: Boolean(id) });
  const { register, handleSubmit, reset, watch, setValue, formState: { errors } } = useForm<FuncionarioFormValues>({
    resolver: zodResolver(funcionarioSchema),
    defaultValues: { tipo_documento: 'CC', estado: 'activo', tipo_vinculacion: 'planta', naturaleza_cargo: 'carrera_administrativa', manual_cargo_version_id: '' },
  });
  useEffect(() => { if (detail.data) { const item = detail.data.data; reset({ ...item, cargo_id: String(item.cargo?.id ?? ''), manual_cargo_version_id: String(item.asignacion_actual?.manual_cargo_version_id ?? ''), correo_institucional: item.correo_institucional ?? '', telefono: item.telefono ?? '', dependencia: item.dependencia ?? '', fecha_ingreso: item.fecha_ingreso ?? '', fecha_retiro: item.fecha_retiro ?? '', tipo_vinculacion: item.asignacion_actual?.tipo_vinculacion ?? 'planta', naturaleza_cargo: item.asignacion_actual?.naturaleza_cargo ?? 'carrera_administrativa' }); } }, [detail.data, reset]);
  const cargoId = watch('cargo_id');
  const fichaId = watch('manual_cargo_version_id');
  const fichas = useQuery({ queryKey: ['manual-fichas', cargoId], enabled: Boolean(cargoId), queryFn: async () => (await apiClient.get<ApiResponse<FichaManual[]>>('/manual-funciones/fichas', { params: { cargo_id: cargoId } })).data.data });
  useEffect(() => {
    if (!fichas.data) return;
    if (fichaId && fichas.data.some((f) => String(f.id) === fichaId)) return;
    const validas = fichas.data.filter((f) => f.vigente);
    setValue('manual_cargo_version_id', validas.length === 1 ? String(validas[0].id) : '');
  }, [fichas.data, fichaId, setValue]);
  const mutation = useMutation({ mutationFn: (values: FuncionarioFormValues) => id ? funcionariosService.update(id, values) : funcionariosService.create(values), onSuccess: (response) => navigate(`/admin/funcionarios/${response.data.id}`, { replace: true }) });

  return (
    <form className="grid gap-5 rounded-xl border border-border bg-white p-6 md:grid-cols-2" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
      <label className="space-y-1 text-sm"><span className="font-medium">Tipo de documento</span><select className="w-full rounded-md border border-border px-3 py-2.5" {...register('tipo_documento')}><option>CC</option><option>CE</option><option>PA</option><option>TI</option></select></label>
      <Input error={errors.numero_documento?.message} label="Número de cédula" {...register('numero_documento')} />
      <Input error={errors.nombres?.message} label="Nombres" {...register('nombres')} />
      <Input error={errors.apellidos?.message} label="Apellidos" {...register('apellidos')} />
      <Input error={errors.correo_institucional?.message} label="Correo institucional" type="email" {...register('correo_institucional')} />
      <Input error={errors.telefono?.message} label="Teléfono" {...register('telefono')} />
      <Input error={errors.dependencia?.message} label="Dependencia" {...register('dependencia')} />
      <label className="space-y-1 text-sm"><span className="font-medium">Cargo</span><select className="w-full rounded-md border border-border px-3 py-2.5" {...register('cargo_id', { onChange: () => setValue('manual_cargo_version_id', '') })}><option value="">Seleccione…</option>{cargos.data?.data.map((item) => <option key={item.id} value={item.id}>{item.denominacion} · {item.codigo}/{item.grado}</option>)}</select>{errors.cargo_id ? <small className="text-villavoRed">{errors.cargo_id.message}</small> : null}</label>
      <Input error={errors.fecha_ingreso?.message} label="Fecha de vinculación" type="date" {...register('fecha_ingreso')} />
      <FichaManualSelect fichas={fichas.data ?? []} value={fichaId ?? ''} onChange={(value) => setValue('manual_cargo_version_id', value, { shouldValidate: true })} loading={fichas.isFetching} error={fichas.isError ? 'No se pudieron cargar las fichas.' : errors.manual_cargo_version_id?.message} />
      <label className="space-y-1 text-sm"><span className="font-medium">Estado</span><select className="w-full rounded-md border border-border px-3 py-2.5" {...register('estado')}><option value="activo">Activo</option><option value="suspendido">Suspendido</option><option value="retirado">Retirado</option></select></label>
      <label className="space-y-1 text-sm"><span className="font-medium">Tipo de vinculación</span><select className="w-full rounded-md border border-border px-3 py-2.5" {...register('tipo_vinculacion')}><option value="planta">Planta</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option><option value="temporal">Temporal</option></select></label>
      <label className="space-y-1 text-sm"><span className="font-medium">Naturaleza del cargo</span><select className="w-full rounded-md border border-border px-3 py-2.5" {...register('naturaleza_cargo')}><option value="carrera_administrativa">Carrera administrativa</option><option value="libre_nombramiento">Libre nombramiento</option><option value="provisional">Provisional</option><option value="encargo">Encargo</option></select></label>
      {mutation.isError ? <p className="md:col-span-2 text-sm text-villavoRed">{getErrorMessage(mutation.error)}</p> : null}
      <div className="md:col-span-2 flex justify-end"><Button disabled={mutation.isPending || fichas.isFetching || fichas.isError || !fichas.data?.some((f) => String(f.id) === fichaId)} icon={<Save size={17} />} type="submit">{mutation.isPending ? 'Guardando…' : 'Guardar funcionario'}</Button></div>
    </form>
  );
}
