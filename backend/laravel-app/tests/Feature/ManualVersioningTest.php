<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\FuncionarioCargoManualFicha;
use App\Models\ManualActualizacionAsignacion;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\CompararVersionesManualService;
use App\Services\PlanificarActualizacionManualService;
use App\Services\PublicarVersionManualService;
use App\Services\ResolverFuncionesFuncionarioService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualVersioningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_actualizacion_normativa_conserva_asignacion_laboral_y_snapshot_historico(): void
    {
        [$actor, $funcionario, $asignacion, $manual, $v10, $a] = $this->escenarioBase();
        $resolver = app(ResolverFuncionesFuncionarioService::class);
        $antes = $resolver->resolver($funcionario, CarbonImmutable::parse('2026-09-30'));
        $c1 = $this->certificado($actor, $funcionario, $antes, 'C1-'.Str::uuid(), '2026-09-01');

        $v11 = $this->version($manual, 'Manual test 11', 'borrador', '2026-10-01');
        $cargoRenumerado = Cargo::create(['codigo' => '997', 'grado' => '01', 'denominacion' => 'Profesional', 'nivel' => 'Profesional', 'estado' => true]);
        $a2 = $this->ficha($v11, $cargoRenumerado, 'OTRO-SOURCE-ID', 'Ejecutar la función nueva.');
        $plan = app(PlanificarActualizacionManualService::class)->ejecutar($v10, $v11);
        $this->assertSame(1, $plan['diff']['conteos']['MODIFICADA']);
        $this->assertSame(1, $plan['asignaciones']['AUTO_MIGRABLE']);

        app(PublicarVersionManualService::class)->publicar($v11, CarbonImmutable::parse('2026-10-01'), $actor->id);

        $this->assertSame($a->id, $resolver->resolver($funcionario, CarbonImmutable::parse('2026-09-30'))['ficha_id']);
        $nuevo = $resolver->resolver($funcionario, CarbonImmutable::parse('2026-10-01'));
        $this->assertSame($a2->id, $nuevo['ficha_id']);
        $this->assertSame('Ejecutar la función nueva.', $nuevo['funciones'][0]['descripcion']);
        $this->assertSame('Ejecutar la función anterior.', $c1->fresh()->snapshot_datos['manual_funciones']['funciones'][0]['descripcion']);
        $c2 = $this->certificado($actor, $funcionario, $nuevo, 'C2-'.Str::uuid(), '2026-10-01');
        $this->assertSame($a2->id, $c2->snapshot_datos['manual_funciones']['ficha_id']);
        $this->assertSame(1, FuncionarioCargo::where('funcionario_id', $funcionario->id)->count());
        $this->assertNull($asignacion->fresh()->fecha_fin);
        $this->assertDatabaseHas('funcionario_cargo_manual_fichas', [
            'funcionario_cargo_id' => $asignacion->id, 'manual_cargo_version_id' => $a2->id,
            'origen' => 'actualizacion_normativa',
        ]);
    }

    public function test_ficha_equivalente_crea_lineage_sin_confiar_en_source_id(): void
    {
        [, , , $manual, $v10, $a] = $this->escenarioBase();
        $v11 = $this->version($manual, 'Manual equivalente', 'borrador', '2026-10-01');
        $a2 = $this->ficha($v11, $a->cargo, 'ID-TOTALMENTE-DISTINTO', 'Ejecutar la función anterior.');

        $result = app(PlanificarActualizacionManualService::class)->ejecutar($v10, $v11);

        $this->assertSame(1, $result['diff']['conteos']['SIN_CAMBIOS']);
        $this->assertDatabaseHas('manual_cargo_lineages', [
            'predecessor_id' => $a->id, 'successor_id' => $a2->id, 'clasificacion' => 'SIN_CAMBIOS',
        ]);
    }

    public function test_ficha_retirada_no_hace_fallback_y_deja_revision(): void
    {
        [$actor, $funcionario, , $manual, $v10] = $this->escenarioBase();
        $v11 = $this->version($manual, 'Manual retiro', 'borrador', '2026-10-01');
        $otroCargo = Cargo::create(['codigo' => '998', 'grado' => '99', 'denominacion' => 'Cargo sin relación', 'nivel' => 'Otro', 'estado' => true]);
        $otra = ManualCargoVersion::create([
            'manual_funciones_version_id' => $v11->id, 'cargo_id' => $otroCargo->id,
            'source_id' => 'NUEVA-1', 'import_key' => hash('sha256', $v11->id.'NUEVA-1'),
            'denominacion_fuente' => 'Conductor', 'dependencia' => 'Infraestructura',
            'area_funcional' => 'Parque automotor', 'proposito_principal' => 'Conducir vehículos institucionales.',
            'metadata_manual' => ['nivel' => 'Asistencial', 'conocimientos' => []],
        ]);
        $otra->funciones()->create(['orden' => 1, 'descripcion' => 'Conducir el vehículo.', 'numero_fuente' => 1, 'grupo' => 'Específicas']);

        $plan = app(PlanificarActualizacionManualService::class)->ejecutar($v10, $v11);
        $this->assertSame(1, $plan['asignaciones']['SIN_EQUIVALENTE']);
        app(PublicarVersionManualService::class)->publicar($v11, CarbonImmutable::parse('2026-10-01'), $actor->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('MANUAL_FICHA_NO_ASIGNADA');
        app(ResolverFuncionesFuncionarioService::class)->resolver($funcionario, CarbonImmutable::parse('2026-10-01'));
    }

    public function test_dos_candidatas_equivalentes_son_ambiguas_y_no_se_elige_la_primera(): void
    {
        [, , , $manual, $v10, $a] = $this->escenarioBase();
        $v11 = $this->version($manual, 'Manual ambiguo', 'borrador', '2026-10-01');
        $this->ficha($v11, $a->cargo, 'NUEVA-A', 'Ejecutar la función anterior.');
        $this->ficha($v11, $a->cargo, 'NUEVA-B', 'Ejecutar la función anterior.');

        $diff = app(CompararVersionesManualService::class)->comparar($v10, $v11);
        $this->assertSame(1, $diff['conteos']['AMBIGUA']);
        $plan = app(PlanificarActualizacionManualService::class)->ejecutar($v10, $v11);
        $this->assertSame(1, $plan['asignaciones']['REQUIERE_REVISION']);
        $this->assertNull(ManualActualizacionAsignacion::firstOrFail()->ficha_candidata_id);
    }

    public function test_contenido_de_version_publicada_es_inmutable_en_base_de_datos(): void
    {
        [, , , , , $a] = $this->escenarioBase();
        $this->expectException(QueryException::class);
        $a->funciones()->firstOrFail()->update(['descripcion' => 'Sobrescritura prohibida']);
    }

    public function test_una_ficha_incompleta_bloquea_solo_al_funcionario_asignado(): void
    {
        $suffix = substr(str_replace('-', '', (string) Str::uuid()), 0, 8);
        $cargo = Cargo::create(['codigo' => substr($suffix, 0, 6), 'grado' => '02', 'denominacion' => 'Técnico', 'nivel' => 'Técnico', 'estado' => true]);
        $manual = ManualFuncion::create(['codigo' => 'INC-'.$suffix, 'nombre' => 'Manual con excepción conocida']);
        $version = $this->version($manual, 'Manual vigente incompleto', 'borrador', '2020-01-01');
        $ficha = ManualCargoVersion::create([
            'manual_funciones_version_id' => $version->id, 'cargo_id' => $cargo->id,
            'source_id' => 'MF-0167', 'import_key' => hash('sha256', $suffix),
            'dependencia' => 'TIC', 'area_funcional' => '', 'proposito_principal' => '',
        ]);
        $ficha->funciones()->create(['orden' => 1, 'descripcion' => 'Atender comunicaciones.']);
        $version->update(['estado' => 'publicado']);
        $funcionario = Funcionario::create(['tipo_documento' => 'CC', 'numero_documento' => 'INC-'.$suffix,
            'nombres' => 'Caso', 'apellidos' => 'Incompleto', 'estado' => 'activo', 'cargo_id' => $cargo->id]);
        $asignacion = FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $cargo->id,
            'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa',
            'fecha_inicio' => '2020-01-01', 'manual_cargo_version_id' => $ficha->id]);
        FuncionarioCargoManualFicha::create(['funcionario_cargo_id' => $asignacion->id,
            'manual_cargo_version_id' => $ficha->id, 'vigencia_desde' => '2020-01-01']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('MANUAL_FICHA_INCOMPLETA');
        app(ResolverFuncionesFuncionarioService::class)->resolver($funcionario, CarbonImmutable::parse('2026-09-30'));
    }

    public function test_solo_baseline_interno_diez_admite_fecha_inicial_desconocida(): void
    {
        $resolver = app(ResolverFuncionesFuncionarioService::class);
        $baseline = new ManualFuncionVersion(['estado' => 'publicado']);
        $baseline->id = 10;
        $this->assertTrue($resolver->versionVigente($baseline, CarbonImmutable::parse('2026-09-19')));
        $baseline->id = 11;
        $this->assertFalse($resolver->versionVigente($baseline, CarbonImmutable::parse('2026-09-19')));
    }

    public function test_publicacion_futura_no_admite_fecha_nula_ni_excepcion_adopcion(): void
    {
        [$actor, , , $manual] = $this->escenarioBase();
        $futura = $this->version($manual, 'Futura sin fecha', 'borrador', null);
        foreach ([false => 'MANUAL_VIGENCIA_DESDE_REQUERIDA', true => 'MANUAL_ADOPCION_NO_PERMITIDA'] as $adopcion => $codigo) {
            try {
                app(PublicarVersionManualService::class)->publicar($futura, null, $actor->id, (bool) $adopcion);
                $this->fail('No debe publicarse una versión futura sin fecha.');
            } catch (DomainException $exception) {
                $this->assertSame($codigo, $exception->getMessage());
            }
        }
        $this->assertSame('borrador', $futura->fresh()->estado);
    }

    private function escenarioBase(): array
    {
        $suffix = substr(str_replace('-', '', (string) Str::uuid()), 0, 8);
        $actor = User::factory()->create();
        $cargo = Cargo::create(['codigo' => substr($suffix, 0, 6), 'grado' => '01', 'denominacion' => 'Profesional', 'nivel' => 'Profesional', 'estado' => true]);
        $manual = ManualFuncion::create(['codigo' => 'MAN-'.$suffix, 'nombre' => 'Manual de prueba']);
        $v10 = $this->version($manual, 'Manual test 10', 'borrador', '2020-01-01');
        $a = $this->ficha($v10, $cargo, 'MF-0123', 'Ejecutar la función anterior.');
        $v10->update(['estado' => 'publicado', 'published_by' => $actor->id, 'published_at' => now()]);
        $funcionario = Funcionario::create([
            'tipo_documento' => 'CC', 'numero_documento' => 'TEST-'.$suffix,
            'nombres' => 'Ana', 'apellidos' => 'Prueba', 'estado' => 'activo', 'cargo_id' => $cargo->id,
        ]);
        $asignacion = FuncionarioCargo::create([
            'funcionario_id' => $funcionario->id, 'cargo_id' => $cargo->id, 'tipo_vinculacion' => 'planta',
            'naturaleza_cargo' => 'carrera_administrativa', 'es_cargo_base' => true, 'es_encargo' => false,
            'fecha_inicio' => '2020-01-01', 'manual_cargo_version_id' => $a->id,
        ]);
        FuncionarioCargoManualFicha::create([
            'funcionario_cargo_id' => $asignacion->id, 'manual_cargo_version_id' => $a->id,
            'vigencia_desde' => '2020-01-01', 'origen' => 'asignacion_inicial',
        ]);

        return [$actor, $funcionario, $asignacion, $manual, $v10, $a];
    }

    private function version(ManualFuncion $manual, string $nombre, string $estado, ?string $desde): ManualFuncionVersion
    {
        return $manual->versiones()->create([
            'version' => $nombre, 'estado' => $estado, 'vigencia_desde' => $desde,
            'acto_tipo' => 'Decreto', 'acto_numero' => Str::random(10), 'acto_fecha' => '2026-01-01',
        ]);
    }

    private function ficha(ManualFuncionVersion $version, Cargo $cargo, string $source, string $funcion): ManualCargoVersion
    {
        $ficha = ManualCargoVersion::create([
            'manual_funciones_version_id' => $version->id, 'cargo_id' => $cargo->id,
            'source_id' => $source, 'import_key' => hash('sha256', $version->id.$source),
            'denominacion_fuente' => 'Profesional', 'dependencia' => 'Talento Humano',
            'area_funcional' => 'Talento Humano', 'proposito_principal' => 'Gestionar procesos institucionales.',
            'metadata_manual' => ['nivel' => 'Profesional', 'conocimientos' => [['numero' => 1, 'texto' => 'Normativa']]],
        ]);
        $ficha->funciones()->create(['orden' => 1, 'descripcion' => $funcion, 'numero_fuente' => 1, 'grupo' => 'Específicas']);

        return $ficha;
    }

    private function certificado(
        User $actor,
        Funcionario $funcionario,
        array $manual,
        string $codigo,
        string $periodo,
    ): Certificado
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id' => $funcionario->id, 'tipo_certificado' => 'funciones', 'estado' => 'aprobada',
            'requiere_pago' => false, 'periodo_mes' => $periodo, 'created_by' => $actor->id,
        ]);

        return Certificado::create([
            'solicitud_certificacion_id' => $solicitud->id, 'funcionario_id' => $funcionario->id,
            'codigo_unico' => $codigo, 'fecha_generacion' => now(), 'generado_por' => $actor->id,
            'estado' => 'vigente', 'snapshot_schema_version' => 2,
            'snapshot_datos' => ['manual_funciones' => $manual],
        ]);
    }
}
