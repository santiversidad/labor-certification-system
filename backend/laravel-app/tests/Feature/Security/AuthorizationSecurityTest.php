<?php

namespace Tests\Feature\Security;

use App\Models\PagoComprobante;

class AuthorizationSecurityTest extends BoundaryTestCase
{
    public function test_payment_responses_minimize_hashes_and_reviewer_identity_by_context(): void
    {
        $id = $this->requestCertificate();
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt()])
            ->assertCreated()->assertJsonMissingPath('data.comprobantes.0.sha256')->assertJsonMissingPath('data.comprobantes.0.revisor');
        $secretary = $this->account('secretario');
        $this->actingAs($secretary, 'sanctum')->postJson("/api/v1/secretaria/pagos/$id/rechazar", ['motivo' => 'Revisión sintética'])
            ->assertOk()->assertJsonPath('data.comprobantes.0.revisor.id', $secretary->id)->assertJsonMissingPath('data.comprobantes.0.sha256');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/solicitudes/$id")
            ->assertOk()->assertJsonPath('data.pago_soporte.comprobantes.0.motivo_rechazo', 'Revisión sintética')
            ->assertJsonMissingPath('data.pago_soporte.comprobantes.0.revisor')
            ->assertJsonMissingPath('data.pago_soporte.comprobantes.0.sha256')
            ->assertJsonMissingPath('data.pago_soporte.validado_por');
        $receipt = PagoComprobante::firstOrFail();
        $this->assertSame(hash_file('sha256', \Illuminate\Support\Facades\Storage::disk('local')->path($receipt->archivo_path)), $receipt->sha256);
    }

    public function test_employee_owns_request_receipt_payment_and_certificate_routes(): void
    {
        $id = $this->requestCertificate();
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt()])->assertCreated();
        $receipt = PagoComprobante::firstOrFail();
        $this->getJson("/api/v1/solicitudes/$id")->assertOk();
        $this->getJson("/api/v1/solicitudes/$id/pago/comprobantes/$receipt->id")->assertOk()->assertHeader('Content-Disposition');
        $this->actingAs($this->other, 'sanctum');
        foreach (["solicitudes/$id", "solicitudes/$id/certificado", "solicitudes/$id/pago/comprobantes/$receipt->id", "pagos/$receipt->pago_soporte_id", "pagos/$receipt->pago_soporte_id/archivo"] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt()])->assertForbidden();
        $this->getJson('/api/v1/solicitudes')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_secretary_has_explicit_review_permission_admin_cannot_confirm(): void
    {
        $id = $this->requestCertificate();
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt()])->assertCreated();
        $secretary = $this->account('secretario');
        $this->actingAs($secretary, 'sanctum')->getJson("/api/v1/secretaria/pagos/$id")->assertOk();
        foreach (['funcionarios', 'admin/usuarios-internos', 'auditoria', 'manual-funciones/estado'] as $path) { $this->getJson('/api/v1/'.$path)->assertForbidden(); }
        $admin = $this->account();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/secretaria/pagos/$id/confirmar")->assertForbidden();
        $this->getJson("/api/v1/solicitudes/$id")->assertOk();
    }

    public function test_foreign_receipt_binding_does_not_cross_requests(): void
    {
        $id = $this->requestCertificate();
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt()])->assertCreated();
        $receipt = PagoComprobante::firstOrFail();
        $this->actingAs($this->other, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])->assertCreated();
        $otherId = \App\Models\SolicitudCertificacion::where('funcionario_id', $this->other->funcionario->id)->value('id');
        $this->getJson("/api/v1/solicitudes/$otherId/pago/comprobantes/$receipt->id")->assertNotFound();
    }
}
