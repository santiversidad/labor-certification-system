import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PaymentStatusNotice } from './components/PaymentStatusNotice';
import { ReceiptUploadForm } from './components/ReceiptUploadForm';
import { ReceiptHistory } from './components/ReceiptHistory';
import { PaymentReviewPage } from './pages/PaymentReviewPage';
import { PaymentReviewDetailPage } from './pages/PaymentReviewDetailPage';
import { SolicitudDetailPage } from '../solicitudes/pages/SolicitudDetailPage';
import { SolicitudForm } from '../solicitudes/components/SolicitudForm';
import { paymentWorkflowService } from './services/paymentWorkflow.service';
import { solicitudesService } from '../solicitudes/services/solicitudes.service';
import type { ComprobantePago, SolicitudEstado, SolicitudPago } from '../solicitudes/types/solicitud.types';

const auth = vi.hoisted(() => ({ user: { tipo_cuenta: 'backoffice', roles: ['secretario'], permisos: ['pagos.ver', 'pagos.validar', 'pagos.rechazar'] } }));
vi.mock('../auth/hooks/useAuth', () => ({ useAuth: () => auth }));
vi.mock('./services/paymentWorkflow.service', () => ({ paymentWorkflowService: { list: vi.fn(), detail: vi.fn(), confirm: vi.fn(), reject: vi.fn(), upload: vi.fn(), download: vi.fn() } }));
vi.mock('../solicitudes/services/solicitudes.service', () => ({ solicitudesService: { getById: vi.fn(), disponibilidad: vi.fn(), create: vi.fn() } }));

const receipt: ComprobantePago = { id: 1, version: 1, nombre_original: 'FICTICIO.pdf', mime_type: 'application/pdf', size_bytes: 120, estado: 'rechazado', motivo_rechazo: 'Comprobante ilegible', archivo_url: '/api/v1/solicitudes/1/pago/comprobantes/1' };
const payment: SolicitudPago = { id: 1, radicado: 'CL-FICTICIO-1', tipo_certificado: 'sencillo', estado: 'pago_en_revision', requiere_pago: true, funcionario: { id: 1, nombres: 'Funcionario', apellidos: 'Ficticio', documento_parcial: '••••0001' }, comprobantes: [receipt], comprobante_max_kb: 5120 };

function mount(element: React.ReactNode, route = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[route]}><Routes><Route path="*" element={element} /><Route path="/detail/:id" element={element} /><Route path="/app/solicitudes/confirmacion" element={<div>Solicitud creada — Pago pendiente</div>} /></Routes></MemoryRouter></QueryClientProvider>);
}

beforeEach(() => {
  vi.resetAllMocks(); auth.user.permisos = ['pagos.ver', 'pagos.validar', 'pagos.rechazar'];
  vi.mocked(paymentWorkflowService.detail).mockResolvedValue({ success: true, data: payment });
  vi.mocked(paymentWorkflowService.list).mockResolvedValue({ success: true, data: [payment] });
  vi.mocked(paymentWorkflowService.confirm).mockResolvedValue({ success: true, data: { ...payment, estado: 'generada' } });
  vi.mocked(paymentWorkflowService.reject).mockResolvedValue({ success: true, data: { ...payment, estado: 'pago_rechazado' } });
  vi.mocked(paymentWorkflowService.upload).mockResolvedValue({ success: true, data: payment });
  vi.mocked(solicitudesService.disponibilidad).mockResolvedValue({ success: true, data: { requiere_pago: true, periodo: '2026-10-01', sencillo: { puede_solicitar: true, proxima_fecha_disponible: null }, funciones: { puede_solicitar: true, proxima_fecha_disponible: null } } });
});

describe('Flujo de pago', () => {
  for (const [estado, title] of [['pendiente_pago', 'Pago pendiente'], ['pago_en_revision', 'Pago en revisión'], ['pago_rechazado', 'Pago no validado'], ['pago_confirmado', 'Pago confirmado'], ['generada', 'Certificado generado'], ['expedicion_fallida', 'Error de expedición']] as [SolicitudEstado, string][]) {
    it(`explica ${estado}`, () => { render(<PaymentStatusNotice estado={estado} motivo="Comprobante ilegible" />); expect(screen.getByText(title)).toBeVisible(); if (estado === 'pago_rechazado') expect(screen.getByText('Comprobante ilegible')).toBeVisible(); });
  }
  it('pago activo crea solicitud y no presenta generación inmediata', async () => {
    vi.mocked(solicitudesService.create).mockResolvedValue({ success: true, data: { resultado: 'pendiente_pago', solicitud: { ...payment, periodo_mes: '2026-10-01', funcionario: undefined } } });
    mount(<SolicitudForm tipo="sencillo" />);
    expect(await screen.findByText('Este certificado requiere pago')).toBeVisible();
    const button = screen.getByRole('button', { name: 'Crear solicitud sencillo' });
    await waitFor(() => expect(button).toBeEnabled()); fireEvent.click(button);
    expect(await screen.findByText('Solicitud creada — Pago pendiente')).toBeVisible();
    expect(solicitudesService.create).toHaveBeenCalledWith(expect.objectContaining({ tipo_certificado: 'sencillo' }), expect.anything());
  });
  it('formulario carga comprobante y conserva misma solicitud al reemplazar', async () => {
    mount(<ReceiptUploadForm id={1} rejected />);
    expect(screen.getByText('Adjuntar nuevo comprobante')).toBeVisible();
    expect(screen.getByRole('button', { name: 'Enviar comprobante' })).toBeDisabled();
    const file = new File(['%PDF-1.4'], 'FICTICIO.pdf', { type: 'application/pdf' });
    fireEvent.change(screen.getByLabelText('Comprobante de pago'), { target: { files: [file] } });
    fireEvent.click(screen.getByRole('button', { name: 'Enviar comprobante' }));
    await waitFor(() => expect(paymentWorkflowService.upload).toHaveBeenCalledWith(1, file, '', ''));
  });
  it('rechaza formato inválido en frontend', async () => {
    mount(<ReceiptUploadForm id={1} rejected={false} />);
    fireEvent.change(screen.getByLabelText('Comprobante de pago'), { target: { files: [new File(['x'], 'pago.exe', { type: 'application/octet-stream' })] } });
    fireEvent.click(screen.getByRole('button', { name: 'Enviar comprobante' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('PDF, JPG/JPEG o PNG');
    expect(paymentWorkflowService.upload).not.toHaveBeenCalled();
  });
  it('historial conserva motivo y ofrece endpoint protegido', async () => {
    mount(<ReceiptHistory comprobantes={[receipt]} />);
    expect(screen.getByText('Motivo: Comprobante ilegible')).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'Ver comprobante 1' }));
    await waitFor(() => expect(paymentWorkflowService.download).toHaveBeenCalledWith(receipt.archivo_url));
  });
  it('Secretaría muestra bandeja y filtro en revisión', async () => {
    mount(<PaymentReviewPage />);
    expect(await screen.findByText('CL-FICTICIO-1')).toBeVisible();
    expect(screen.getByRole('combobox')).toHaveValue('pago_en_revision');
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'generada' } });
    await waitFor(() => expect(paymentWorkflowService.list).toHaveBeenCalledWith('generada', 1));
  });
  it('Secretaría confirma desde detalle con diálogo explícito', async () => {
    mount(<PaymentReviewDetailPage />, '/detail/1');
    fireEvent.click(await screen.findByRole('button', { name: 'Confirmar pago' }));
    const buttons = screen.getAllByRole('button', { name: 'Confirmar pago' }); fireEvent.click(buttons.at(-1)!);
    await waitFor(() => expect(paymentWorkflowService.confirm).toHaveBeenCalledWith(1));
  });
  it('Secretaría requiere motivo para rechazar', async () => {
    mount(<PaymentReviewDetailPage />, '/detail/1');
    fireEvent.click(await screen.findByRole('button', { name: 'Rechazar pago' }));
    const button = screen.getByRole('button', { name: 'Registrar rechazo' }); expect(button).toBeDisabled();
    fireEvent.change(screen.getByLabelText('Motivo del rechazo'), { target: { value: 'Comprobante ilegible' } }); fireEvent.click(button);
    await waitFor(() => expect(paymentWorkflowService.reject).toHaveBeenCalledWith(1, 'Comprobante ilegible'));
  });
  it('usuario sin permisos no ve acciones de revisión', async () => {
    auth.user.permisos = ['pagos.ver']; mount(<PaymentReviewDetailPage />, '/detail/1');
    await screen.findByText('Revisión del pago');
    expect(screen.queryByRole('button', { name: 'Confirmar pago' })).not.toBeInTheDocument(); expect(screen.queryByRole('button', { name: 'Rechazar pago' })).not.toBeInTheDocument();
  });
  for (const estado of ['pago_confirmado', 'generada'] as SolicitudEstado[]) {
    it(`descarga del titular disponible únicamente en generada (${estado})`, async () => {
      vi.mocked(solicitudesService.getById).mockResolvedValue({ success: true, data: { ...payment, estado, periodo_mes: '2026-10-01', funcionario: undefined } });
      mount(<SolicitudDetailPage />, '/detail/1'); await screen.findByText('Solicitud CL-FICTICIO-1');
      if (estado === 'generada') expect(screen.getByRole('button', { name: 'Descargar PDF' })).toBeVisible(); else expect(screen.queryByRole('button', { name: 'Descargar PDF' })).not.toBeInTheDocument();
    });
  }
});
