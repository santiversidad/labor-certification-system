<?php

namespace Tests\Feature\Security;

use App\Models\SolicitudCertificacion;

class InputSecurityTest extends BoundaryTestCase
{
    public function test_client_cannot_assign_owner_payment_state_or_privileges(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo', 'funcionario_id' => $this->other->funcionario->id])->assertUnprocessable();
        $this->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo', 'role' => 'admin', 'tipo_cuenta' => 'backoffice',
            'estado' => 'generada', 'requiere_pago' => false, 'pago_confirmado' => true, 'snapshot' => ['injected' => true],
            'revisado_por' => 123, 'certificado_id' => 123, 'auth_version' => 999, 'must_change_password' => false])->assertCreated();
        $request = SolicitudCertificacion::firstOrFail();
        $this->assertSame($this->owner->funcionario->id, $request->funcionario_id);
        $this->assertSame('pendiente_pago', $request->estado->value); $this->assertTrue($request->requiere_pago);
        $this->assertTrue($this->owner->fresh()->hasRole('funcionario'));
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_backoffice_update_rejects_extra_sensitive_fields(): void
    {
        $actor = $this->account(); $target = $this->account('secretario');
        $this->actingAs($actor, 'sanctum')->patchJson('/api/v1/admin/usuarios-internos/'.$target->id,
            ['name' => 'Unchanged', 'auth_version' => 999, 'role' => 'admin', 'mfa_secret' => 'attacker'])->assertUnprocessable();
        $this->assertTrue($target->fresh()->hasRole('secretario')); $this->assertNull($target->fresh()->mfa_secret);
    }

    public function test_synthetic_xss_stays_text_in_observation_and_search(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo', 'observaciones' => $payload])->assertCreated();
        $this->assertSame($payload, SolicitudCertificacion::firstOrFail()->observaciones);
        $admin = $this->account();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/funcionarios?q='.urlencode($payload))->assertOk()->assertJsonPath('meta.total', 0);
    }
}
