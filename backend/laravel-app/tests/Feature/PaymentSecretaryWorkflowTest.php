<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualFuncion;
use App\Models\PagoComprobante;
use App\Models\ParametroSistema;
use App\Models\User;
use App\Services\CertificadoPdfService;
use App\Services\PagoSolicitudService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class PaymentSecretaryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $titular;

    private User $otro;

    private User $secretaria;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');
        $cargo = Cargo::create(['codigo' => 'P27', 'grado' => '01', 'denominacion' => 'Cargo ficticio', 'nivel' => 'Profesional', 'estado' => true]);
        $this->titular = $this->empleado($cargo, 'PAGO-1');
        $this->otro = $this->empleado($cargo, 'PAGO-2');
        $this->secretaria = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $this->secretaria->assignRole('secretario');
        $this->admin = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $this->admin->assignRole('admin');
        ParametroSistema::create(['clave' => 'requiere_pago_certificado', 'valor' => 'true', 'tipo' => 'boolean']);
    }

    private function empleado(Cargo $cargo, string $documento): User
    {
        $user = User::factory()->create(['documento' => $documento, 'estado' => true, 'must_change_password' => false]);
        $user->assignRole('funcionario');
        $funcionario = Funcionario::create(['user_id' => $user->id, 'tipo_documento' => 'CC', 'numero_documento' => $documento,
            'nombres' => 'Funcionario', 'apellidos' => 'Ficticio pago', 'estado' => 'activo', 'cargo_id' => $cargo->id, 'fecha_ingreso' => '2020-01-01']);
        FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $cargo->id, 'tipo_vinculacion' => 'planta',
            'naturaleza_cargo' => 'carrera_administrativa', 'fecha_inicio' => '2020-01-01', 'es_cargo_base' => true]);
        ManualFixture::vincular($funcionario);

        return $user;
    }

    private function solicitud(string $tipo = 'sencillo'): int
    {
        return $this->actingAs($this->titular, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => $tipo])
            ->assertCreated()->json('data.solicitud.id');
    }

    private function archivo(string $nombre = 'comprobante.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n% Comprobante ficticio, no corresponde a un pago real.\n");
    }

    private function cargar(int $id)
    {
        return $this->actingAs($this->titular, 'sanctum')->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => $this->archivo(), 'referencia' => 'FICTICIA-1']);
    }

    private function confirmar(int $id)
    {
        return $this->actingAs($this->secretaria, 'sanctum')->postJson("/api/v1/secretaria/pagos/{$id}/confirmar");
    }

    public function test_pago_on_reserva_cupo_no_genera_y_no_duplica_solicitud(): void
    {
        $id = $this->solicitud();
        $this->assertDatabaseHas('solicitudes_certificacion', ['id' => $id, 'estado' => 'pendiente_pago', 'requiere_pago' => true]);
        $this->assertDatabaseCount('certificados', 0);
        $this->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])->assertConflict()->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        $this->assertDatabaseCount('solicitudes_certificacion', 1);
        $this->getJson("/api/v1/solicitudes/{$id}/certificado")->assertConflict();
    }

    public function test_upload_privado_guarda_hash_metadata_y_auditoria(): void
    {
        $id = $this->solicitud();
        $r = $this->cargar($id)->assertCreated()->assertJsonPath('data.estado', 'pago_en_revision');
        $c = PagoComprobante::firstOrFail();
        Storage::disk('local')->assertExists($c->archivo_path);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($c->archivo_path)), $c->sha256);
        $this->assertStringNotContainsString('comprobante.pdf', $c->archivo_path);
        $this->assertStringNotContainsString($c->archivo_path, $r->getContent());
        $this->assertDatabaseHas('audit_logs', ['accion' => 'comprobante_cargado', 'user_id' => $this->titular->id]);
        $this->get($r->json('data.comprobantes.0.archivo_url'))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_upload_rechaza_mime_falso_y_extension_incompatible(): void
    {
        $id = $this->solicitud();
        foreach ([$this->archivo('pago.exe'), UploadedFile::fake()->createWithContent('pago.pdf', '<?php echo 1;')] as $archivo) {
            $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => $archivo])->assertUnprocessable();
        }
        $this->assertDatabaseCount('pago_comprobantes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_rechaza_limite_configurable(): void
    {
        config(['payments.receipt_max_kb' => 1]);
        $id = $this->solicitud();
        $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => UploadedFile::fake()->createWithContent('pago.pdf', '%PDF-1.4'.str_repeat('x', 2048))])->assertUnprocessable();
    }

    public function test_upload_rechaza_mas_de_cinco_mb_y_no_crea_evidencia(): void
    {
        $id = $this->solicitud();
        $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => UploadedFile::fake()->createWithContent('grande.pdf', '%PDF-1.4'.str_repeat('x', 5120 * 1024))])->assertUnprocessable();
        $this->assertDatabaseCount('pago_comprobantes', 0);
    }

    public function test_png_y_jpeg_validos_son_admitidos_y_descargables(): void
    {
        foreach (['png' => 'sencillo', 'jpg' => 'funciones'] as $extension => $tipo) {
            $id = $this->solicitud($tipo);
            $r = $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => UploadedFile::fake()->createWithContent('ficticio.'.$extension, file_get_contents(base_path('tests/Fixtures/payments/ficticio.'.$extension)))])->assertCreated();
            $this->get($r->json('data.comprobantes.0.archivo_url'))->assertOk();
        }
    }

    public function test_anonimo_no_ve_detalle_ni_carga_o_descarga(): void
    {
        $id = $this->solicitud();
        $r = $this->cargar($id)->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/solicitudes/{$id}")->assertUnauthorized();
        $this->getJson($r->json('data.comprobantes.0.archivo_url'))->assertUnauthorized();
        $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => $this->archivo()])->assertUnauthorized();
        $this->postJson("/api/v1/secretaria/pagos/{$id}/confirmar")->assertUnauthorized();
    }

    public function test_secretaria_sin_permiso_confirmar_y_comprobante_ausente(): void
    {
        $id = $this->solicitud();
        $r = $this->cargar($id)->assertCreated();
        Storage::disk('local')->delete(PagoComprobante::firstOrFail()->archivo_path);
        $this->confirmar($id)->assertConflict()->assertJsonPath('code', 'COMPROBANTE_REQUERIDO');
        Role::findByName('secretario', 'web')->revokePermissionTo('pagos.validar');
        $this->secretaria = $this->secretaria->fresh();
        $this->confirmar($id)->assertForbidden();
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_doble_upload_solo_admite_una_evidencia_en_revision(): void
    {
        $id = $this->solicitud();
        $this->cargar($id)->assertCreated();
        $this->cargar($id)->assertConflict()->assertJsonPath('code', 'TRANSICION_PAGO_INVALIDA');
        $this->assertDatabaseCount('pago_comprobantes', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_secretaria_lista_detalle_minimo_y_confirma_con_snapshot_v3_pdf(): void
    {
        $id = $this->solicitud('funciones');
        $this->cargar($id)->assertCreated();
        $this->actingAs($this->secretaria, 'sanctum')->getJson('/api/v1/secretaria/pagos')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.funcionario.numero_documento');
        $this->getJson("/api/v1/secretaria/pagos/{$id}")->assertOk()->assertJsonCount(1, 'data.comprobantes');
        $this->confirmar($id)->assertOk()->assertJsonPath('data.estado', 'generada');
        $certificado = Certificado::firstOrFail();
        $this->assertSame(3, $certificado->snapshot_schema_version);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($certificado->archivo_pdf_path));
        $this->assertDatabaseHas('pagos_soportes', ['estado' => 'confirmado', 'validado_por' => $this->secretaria->id]);
        $this->assertDatabaseHas('ordenes_pago_certificado', ['estado' => 'confirmado']);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'pago_confirmado', 'user_id' => $this->secretaria->id]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'generacion_posterior_pago', 'user_id' => $this->secretaria->id]);
        $this->actingAs($this->titular, 'sanctum')->get("/api/v1/solicitudes/{$id}/certificado")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_rechazo_motivo_obligatorio_reemplazo_misma_solicitud_con_historial(): void
    {
        $id = $this->solicitud();
        $this->cargar($id)->assertCreated();
        $this->actingAs($this->secretaria, 'sanctum');
        foreach ([[], ['motivo' => '   ']] as $datos) {
            $this->postJson("/api/v1/secretaria/pagos/{$id}/rechazar", $datos)->assertUnprocessable();
        }
        $this->postJson("/api/v1/secretaria/pagos/{$id}/rechazar", ['motivo' => 'Comprobante ilegible'])->assertOk()->assertJsonPath('data.estado', 'pago_rechazado');
        $this->assertDatabaseCount('certificados', 0);
        $primero = PagoComprobante::firstOrFail();
        $this->actingAs($this->titular, 'sanctum')->getJson("/api/v1/solicitudes/{$id}")->assertOk()->assertJsonPath('data.motivo_rechazo', 'Comprobante ilegible');
        $this->cargar($id)->assertCreated()->assertJsonCount(2, 'data.comprobantes');
        Storage::disk('local')->assertExists($primero->archivo_path);
        $this->confirmar($id)->assertOk()->assertJsonPath('data.comprobantes.0.estado', 'rechazado')->assertJsonPath('data.comprobantes.1.estado', 'confirmado');
        $this->assertDatabaseCount('solicitudes_certificacion', 1);
        $this->assertDatabaseCount('certificados', 1);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'comprobante_reemplazado', 'user_id' => $this->titular->id]);
    }

    public function test_doble_confirmacion_no_duplica_y_rechazo_posterior_falla(): void
    {
        $id = $this->solicitud();
        $this->cargar($id)->assertCreated();
        $this->confirmar($id)->assertOk();
        $this->confirmar($id)->assertOk();
        $this->postJson("/api/v1/secretaria/pagos/{$id}/rechazar", ['motivo' => 'Fuera de estado'])->assertConflict();
        $this->assertDatabaseCount('certificados', 1);
        $this->assertSame(1, AuditLog::where('accion', 'pago_confirmado')->count());
    }

    public function test_confirmacion_sin_comprobante_no_avanza(): void
    {
        $id = $this->solicitud();
        $this->confirmar($id)->assertConflict();
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_fallo_tecnico_conserva_pago_y_retry_explicito(): void
    {
        $id = $this->solicitud();
        $this->cargar($id)->assertCreated();
        $this->mock(CertificadoPdfService::class)->shouldReceive('generarDesdeSnapshot')->once()->andThrow(new \RuntimeException('PDF_STORAGE_FAILED'));
        $this->confirmar($id)->assertOk()->assertJsonPath('data.estado', 'expedicion_fallida');
        $this->confirmar($id)->assertOk(); // No implicit retry.
        $this->assertDatabaseHas('ordenes_pago_certificado', ['estado' => 'confirmado']);
        $this->assertDatabaseHas('pago_comprobantes', ['estado' => 'confirmado']);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'expedicion_pago_fallida', 'user_id' => $this->secretaria->id]);
        $this->actingAs($this->titular, 'sanctum')->getJson("/api/v1/solicitudes/{$id}/certificado")->assertConflict();
        $this->app->forgetInstance(CertificadoPdfService::class);
        $this->app->forgetInstance(PagoSolicitudService::class);
        app(PagoSolicitudService::class)->expedirConfirmada($id, $this->secretaria, true);
        $this->assertDatabaseHas('solicitudes_certificacion', ['id' => $id, 'estado' => 'generada']);
    }

    public function test_cambio_parametro_no_cambia_regimen_historico(): void
    {
        $id = $this->solicitud();
        ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => 'false']);
        $this->cargar($id)->assertCreated();
        $this->confirmar($id)->assertOk();
        $idOff = $this->solicitud('funciones');
        ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => 'true']);
        $this->assertDatabaseHas('solicitudes_certificacion', ['id' => $idOff, 'requiere_pago' => false, 'estado' => 'generada']);
        $this->cargar($idOff)->assertConflict();
    }

    public function test_ownership_detalle_listado_upload_y_descarga_a_b(): void
    {
        $id = $this->solicitud();
        $r = $this->cargar($id)->assertCreated();
        $this->actingAs($this->otro, 'sanctum')->getJson("/api/v1/solicitudes/{$id}")->assertForbidden();
        $this->getJson('/api/v1/solicitudes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($r->json('data.comprobantes.0.archivo_url'))->assertForbidden();
        $this->postJson("/api/v1/solicitudes/{$id}/pago/comprobantes", ['archivo' => $this->archivo(), 'funcionario_id' => $this->titular->funcionario->id])->assertForbidden();
        $this->getJson("/api/v1/solicitudes/{$id}/certificado")->assertForbidden();
    }

    public function test_401_403_permiso_y_rol_secretaria(): void
    {
        $this->getJson('/api/v1/secretaria/pagos')->assertUnauthorized();
        $id = $this->solicitud();
        $this->cargar($id)->assertCreated();
        foreach ([$this->titular, $this->admin, User::factory()->create(['estado' => true])] as $user) {
            $this->actingAs($user, 'sanctum')->postJson("/api/v1/secretaria/pagos/{$id}/confirmar")->assertForbidden();
        }
        $this->secretaria->syncRoles([]);
        $this->actingAs($this->secretaria->fresh(), 'sanctum')->getJson('/api/v1/secretaria/pagos')->assertForbidden();
    }

    public function test_integridad_y_descarga_con_permiso_explicito(): void
    {
        $id = $this->solicitud();
        $r = $this->cargar($id)->assertCreated();
        $url = $r->json('data.comprobantes.0.archivo_url');
        $this->actingAs($this->admin, 'sanctum')->get($url)->assertOk();
        $c = PagoComprobante::firstOrFail();
        Storage::disk('local')->put($c->archivo_path, 'corrupto');
        $this->getJson($url)->assertConflict()->assertJsonPath('code', 'COMPROBANTE_INTEGRIDAD_INVALIDA');
        $this->confirmar($id)->assertConflict();
    }

    public function test_ficha_incompleta_en_expedicion_conserva_pago_y_sencillo_no_depende_manual(): void
    {
        $id = $this->solicitud('funciones');
        $this->cargar($id)->assertCreated();
        $manual = ManualFuncion::create(['codigo' => 'P27-INCOMPLETO', 'nombre' => 'Manual ficticio incompleto']);
        $version = $manual->versiones()->create(['version' => 'P27-INC', 'estado' => 'borrador', 'acto_tipo' => 'Prueba', 'acto_numero' => 'P27', 'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01']);
        $ficha = $version->cargos()->create(['cargo_id' => $this->titular->funcionario->cargo_id, 'source_id' => 'MF-0167', 'area_funcional' => '', 'proposito_principal' => '']);
        $ficha->funciones()->create(['orden' => 1, 'descripcion' => 'Función ficticia.']);
        $version->update(['estado' => 'publicado']);
        $this->titular->funcionario->historialCargos()->first()->update(['manual_cargo_version_id' => $ficha->id]);
        $this->confirmar($id)->assertOk()->assertJsonPath('data.estado', 'expedicion_fallida')->assertJsonPath('data.error_expedicion', 'MANUAL_FICHA_INCOMPLETA');
        $idSencillo = $this->solicitud();
        $this->cargar($idSencillo)->assertCreated();
        $this->confirmar($idSencillo)->assertOk()->assertJsonPath('data.estado', 'generada');
    }
}
