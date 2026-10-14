import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { CertificateTemplateEditor, CertificateTemplateEditorPage } from '../pages/CertificateTemplateEditorPage';
import { CertificateTemplatesPage } from '../pages/CertificateTemplatesPage';
import { templatesService } from '../services/templates.service';
import { displayRuns, parseRuns, templateIssues } from '../schemas/template.schema';
import type { TemplateDetail, Variable } from '../types/template.types';

const auth = vi.hoisted(() => ({ roles: ['admin'] }));
vi.mock('../../auth/hooks/useAuth', () => ({ useAuth: () => ({ user: { roles: auth.roles, tipo_cuenta: 'backoffice' } }) }));
vi.mock('../services/templates.service', () => ({ templatesService: { list: vi.fn(), catalog: vi.fn(), create: vi.fn(), detail: vi.fn(), history: vi.fn(), save: vi.fn(), publish: vi.fn(), preview: vi.fn(), upload: vi.fn() } }));
vi.mock('../components/TemplatePreview', () => ({ TemplatePreview: () => <div>PDF sintético renderizado</div> }));
const variables: Variable[] = [{ id: 'nombre_completo', etiqueta: 'Nombre completo', grupo: 'Identificación', descripcion: 'Nombre del snapshot.', tipo: 'texto', origen: 'snapshot', formato: 'texto', disponibilidad: 'ambos', ausencia: 'bloquear' }, { id: 'fecha_retiro', etiqueta: 'Fecha de retiro', grupo: 'Vinculación', descripcion: 'Retiro registrado.', tipo: 'fecha', origen: 'snapshot', formato: 'fecha', disponibilidad: 'ambos', ausencia: 'omitir_parrafo' }];
function detail(): TemplateDetail {
  return { id: 4, plantilla_id: 2, numero: 1, revision: 1, estado: 'borrador', tipo: 'funciones', sha256: 'a'.repeat(64), updated_at: '2026-10-12T12:00:00Z', publicado_at: null, ultimo_editor: 'Administrador sintético', assets: [], contenido: { schema_version: 1, title: 'CERTIFICACIÓN SINTÉTICA', header: { left_asset_id: null, right_asset_id: null }, typography: { font: 'DejaVu Sans', size: 10, line_height: 1.4 }, paragraphs: [{ id: 'certificacion', position: 'body', alignment: 'justify', runs: [{ type: 'text', text: 'Se certifica a ' }, { type: 'variable', variable: 'nombre_completo' }] }, { id: 'cierre', position: 'closing', alignment: 'left', runs: [{ type: 'text', text: 'Se expide a solicitud.' }] }], functions: { title: 'FUNCIONES', size: 10, line_height: 1.4 }, signature: { name: '', role: 'Dirección de Personal', office: '', text: '', alignment: 'center' }, additional_signatures: [], footer: { direccion: '', nit: '', telefono: '', codigo_postal: '', sitio_web: '', cuenta_x: '', ciudad: '' } } };
}
beforeEach(() => {
  vi.resetAllMocks(); auth.roles = ['admin'];
  vi.mocked(templatesService.list).mockResolvedValue([{ id: 1, tipo: 'sencillo', publicada: null, borrador: null }, { id: 2, tipo: 'funciones', publicada: null, borrador: detail() }]);
  vi.mocked(templatesService.detail).mockResolvedValue(detail());
  vi.mocked(templatesService.catalog).mockResolvedValue({ variables, fonts: ['DejaVu Sans', 'DejaVu Serif'] });
  vi.mocked(templatesService.preview).mockResolvedValue(new Blob(['%PDF-1.7'], { type: 'application/pdf' }));
  vi.mocked(templatesService.save).mockImplementation(async (_id, revision, contenido) => ({ ...detail(), revision: revision + 1, contenido }));
  vi.mocked(templatesService.publish).mockResolvedValue({ ...detail(), estado: 'publicada' });
  vi.mocked(templatesService.history).mockResolvedValue({ success: true, data: [detail()], meta: { current_page: 1, last_page: 2, total: 26, per_page: 25 } });
});
function setup(node = <CertificateTemplateEditor initial={detail()} variables={variables} fonts={['DejaVu Sans', 'DejaVu Serif']} />) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<MemoryRouter><QueryClientProvider client={client}>{node}</QueryClientProvider></MemoryRouter>);
}
function change(label: string, value: string) { fireEvent.change(screen.getByLabelText(label), { target: { value } }); }
async function open(title: string) { await userEvent.click(screen.getByText(title, { exact: true })); }
async function save() { await userEvent.click(screen.getByRole('button', { name: 'Guardar borrador' })); await screen.findByText('Borrador guardado.'); }

it('muestra exactamente las dos plantillas y su estado legacy o borrador', async () => {
  setup(<CertificateTemplatesPage />);
  expect(await screen.findByRole('region', { name: 'Certificado sencillo' })).toHaveTextContent('Formato actual (legacy)');
  expect(screen.getByRole('region', { name: 'Certificado con funciones' })).toHaveTextContent('Versión 1');
  expect(screen.getAllByRole('region')).toHaveLength(2);
});
it('consulta historial paginado solamente al abrirlo', async () => {
  setup(<CertificateTemplatesPage />); await screen.findByText('Certificado sencillo');
  expect(templatesService.history).not.toHaveBeenCalled();
  await userEvent.click(within(screen.getByRole('region', { name: 'Certificado con funciones' })).getByRole('button', { name: 'Historial' }));
  expect(await screen.findByText('Versión 1 · borrador')).toBeVisible();
  await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }));
  await waitFor(() => expect(templatesService.history).toHaveBeenLastCalledWith(2, 2));
});
it.each(['secretario', 'funcionario'])('no consulta ni muestra editor a %s', (role) => {
  auth.roles = [role]; setup(<CertificateTemplateEditorPage />);
  expect(screen.getByRole('alert')).toHaveTextContent('únicamente para administradores');
  expect(templatesService.detail).not.toHaveBeenCalled(); expect(templatesService.catalog).not.toHaveBeenCalled();
});
it('expone carga y reintento sin editor vacío', async () => {
  vi.mocked(templatesService.list).mockRejectedValueOnce(new Error('Temporal')); setup(<CertificateTemplatesPage />);
  expect(screen.getByRole('status')).toHaveTextContent('Cargando');
  await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));
  expect(await screen.findByText('Certificado sencillo')).toBeVisible();
});
it('guarda título con revisión y no publica sin una vista previa vigente', async () => {
  setup(); change('Título del certificado', 'TÍTULO REVISADO');
  expect(screen.getByRole('button', { name: 'Publicar versión' })).toBeDisabled(); await save();
  expect(templatesService.save).toHaveBeenCalledWith(4, 1, expect.objectContaining({ title: 'TÍTULO REVISADO' }));
  expect(templatesService.publish).not.toHaveBeenCalled();
});
it('inserta variable autorizada en el cursor y conserva espacios del párrafo', async () => {
  setup(); const field = screen.getByLabelText('Texto del párrafo 1') as HTMLTextAreaElement;
  fireEvent.change(field, { target: { value: 'Inicio final' } }); field.setSelectionRange(7, 7);
  await userEvent.click(within(screen.getByRole('region', { name: 'Párrafo 1' })).getByRole('button', { name: 'Insertar variable' }));
  change('Buscar variable', 'retiro');
  expect(screen.getByText(/Si no hay dato, se omite el párrafo/)).toBeVisible();
  await userEvent.click(screen.getByRole('button', { name: /Fecha de retiro/ })); await save();
  expect(vi.mocked(templatesService.save).mock.calls[0][2].paragraphs[0].runs).toEqual([{ type: 'text', text: 'Inicio ', bold: false, italic: false }, { type: 'variable', variable: 'fecha_retiro', bold: false, italic: false }, { type: 'text', text: 'final', bold: false, italic: false }]);
});
it('agrega, reordena y elimina solo párrafos opcionales', async () => {
  setup(); expect(screen.queryByRole('button', { name: 'Eliminar párrafo 1' })).not.toBeInTheDocument();
  expect(screen.getByLabelText('Sección del párrafo 1')).toBeDisabled();
  await userEvent.click(screen.getByRole('button', { name: 'Agregar párrafo' })); change('Texto del párrafo 3', 'Párrafo opcional');
  await userEvent.click(screen.getByRole('button', { name: 'Subir párrafo 3' }));
  expect(screen.getByLabelText('Texto del párrafo 2')).toHaveValue('Párrafo opcional');
  await userEvent.click(screen.getByRole('button', { name: 'Eliminar párrafo 2' }));
  expect(screen.getAllByRole('region', { name: /Párrafo/ })).toHaveLength(2);
});
it('configura negrita, cursiva y alineación sin HTML', async () => {
  setup(); const region = screen.getByRole('region', { name: 'Párrafo 1' });
  await userEvent.click(within(region).getByLabelText('Negrita')); await userEvent.click(within(region).getByLabelText('Cursiva'));
  change('Alineación del párrafo 1', 'center'); await save();
  const paragraph = vi.mocked(templatesService.save).mock.calls[0][2].paragraphs[0];
  expect(paragraph.alignment).toBe('center'); expect(paragraph.runs.every((run) => run.bold && run.italic)).toBe(true);
});
it('rechaza tokens desconocidos y HTML antes de guardar', () => {
  setup(); change('Texto del párrafo 1', '<script>alert(1)</script> [salario]');
  expect(screen.getByRole('alert')).toHaveTextContent('no se admite HTML');
  expect(screen.getByRole('button', { name: 'Guardar borrador' })).toBeDisabled();
});
it('configura fuente, funciones, firma principal y pie sin reescribir funciones', async () => {
  setup(); await open('Tipografía'); change('Familia tipográfica', 'DejaVu Serif'); change('Tamaño de letra', '11');
  await open('Funciones del cargo'); change('Título de la sección de funciones', 'FUNCIONES NORMATIVAS');
  expect(screen.queryByLabelText('Texto de funciones')).not.toBeInTheDocument();
  await open('Firma institucional'); change('Nombre del firmante', 'Firmante sintético'); change('Alineación de firma', 'right');
  await open('Pie de página'); change('Dirección', 'Dirección sintética'); await save();
  expect(vi.mocked(templatesService.save).mock.calls[0][2]).toEqual(expect.objectContaining({ typography: expect.objectContaining({ font: 'DejaVu Serif', size: 11 }), functions: expect.objectContaining({ title: 'FUNCIONES NORMATIVAS' }), signature: expect.objectContaining({ name: 'Firmante sintético', alignment: 'right' }), footer: expect.objectContaining({ direccion: 'Dirección sintética' }) }));
});
it('limita las firmas adicionales a tres filas de texto y permite eliminarlas', async () => {
  setup(); await open('Firmas adicionales');
  for (let i = 0; i < 3; i++) await userEvent.click(screen.getByRole('button', { name: 'Agregar firma adicional' }));
  expect(screen.getByRole('button', { name: 'Agregar firma adicional' })).toBeDisabled();
  change('Etiqueta adicional 1', 'Revisó'); change('Nombre adicional 1', 'Revisor sintético');
  await userEvent.click(screen.getByRole('button', { name: 'Eliminar firma adicional 3' })); await save();
  expect(vi.mocked(templatesService.save).mock.calls[0][2].additional_signatures).toHaveLength(2);
});
it('previsualiza cambios sin guardar, exige guardarlos y confirmar publicación', async () => {
  setup(); change('Título del certificado', 'Título editado'); await userEvent.click(screen.getByRole('button', { name: 'Generar vista previa' }));
  await screen.findByText('PDF sintético renderizado'); expect(templatesService.preview).toHaveBeenCalledWith(4, expect.objectContaining({ title: 'Título editado' }), 'breve');
  expect(screen.getByRole('button', { name: 'Publicar versión' })).toBeDisabled(); await save();
  await userEvent.click(screen.getByRole('button', { name: 'Publicar versión' })); expect(templatesService.publish).not.toHaveBeenCalled();
  expect(screen.getByRole('dialog')).toHaveTextContent('Los documentos anteriores conservarán su PDF');
  await userEvent.click(screen.getByRole('button', { name: 'Confirmar publicación' }));
  await waitFor(() => expect(templatesService.publish).toHaveBeenCalledWith(4, 2));
  expect(await screen.findByText(/Esta versión es inmutable/)).toBeVisible();
});
it('invalida vista previa al cambiar contenido y muestra conflictos de revisión', async () => {
  setup(); await userEvent.click(screen.getByRole('button', { name: 'Generar vista previa' })); await screen.findByText('PDF sintético renderizado');
  change('Título del certificado', 'Cambio posterior'); expect(screen.queryByText('PDF sintético renderizado')).not.toBeInTheDocument();
  vi.mocked(templatesService.save).mockRejectedValue({ response: { data: { message: 'Otro administrador actualizó el borrador.' } } });
  await userEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Otro administrador');
});
it('sube imagen con procedencia y guarda su referencia, sin rutas arbitrarias', async () => {
  const asset = { id: 7, posicion: 'left' as const, sha256: 'b'.repeat(64), mime: 'image/png', ancho: 200, alto: 200, bytes: 200, procedencia: 'Prueba sintética', url: '/api/v1/admin/plantillas-certificados/assets/7' };
  vi.mocked(templatesService.upload).mockResolvedValue(asset); setup(); await open('Identidad visual');
  expect(screen.getByLabelText('Reemplazar escudo')).toBeDisabled(); change('Procedencia de la nueva imagen izquierda', 'Prueba sintética');
  await userEvent.upload(screen.getByLabelText('Reemplazar escudo'), new File(['synthetic'], 'logo.png', { type: 'image/png' }));
  await screen.findByText(/Imagen validada/); await save();
  expect(vi.mocked(templatesService.save).mock.calls[0][2].header.left_asset_id).toBe(7);
  expect(screen.getByAltText('Escudo institucional')).toHaveClass('object-contain');
});
it('sencillo oculta funciones y versión publicada deshabilita edición', () => {
  const version = detail(); version.tipo = 'sencillo'; version.contenido.functions = null; version.estado = 'publicada';
  setup(<CertificateTemplateEditor initial={version} variables={variables} fonts={['DejaVu Sans']} />);
  expect(screen.queryByText('Funciones del cargo', { exact: true })).not.toBeInTheDocument();
  expect(screen.getByLabelText('Título del certificado')).toBeDisabled(); expect(screen.getByRole('button', { name: 'Crear nueva versión' })).toBeEnabled();
});
it('convierte etiquetas legibles en tokens permitidos y conserva literales', () => {
  const runs = parseRuns('A [Nombre completo] B', variables, true);
  expect(displayRuns(runs, variables)).toBe('A [Nombre completo] B');
  const content = detail().contenido; content.paragraphs[0].runs = parseRuns('[objeto.ruta]', variables);
  expect(templateIssues(content)).toContain('desconocida'); content.typography.size = 13; expect(templateIssues(content)).toContain('9 y 12');
});
