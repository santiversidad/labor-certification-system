<?php

namespace Tests\Feature;

use App\Domain\SalaryScale\SalaryScaleAmount;
use App\Models\AuditLog;
use App\Models\Cargo;
use App\Models\EscalaSalarialVigencia;
use App\Models\User;
use App\Services\SalaryScaleImportService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalaryScaleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private const URL = '/api/v1/escala-salarial';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->backoffice()->create(['estado' => true]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin, 'sanctum');
    }

    private function base(int $year = 2025): EscalaSalarialVigencia
    {
        $base = EscalaSalarialVigencia::create(['anio' => $year, 'fuente' => 'Fuente sintética', 'creado_por' => $this->admin->id]);
        $base->items()->create(['codigo' => '099', 'grado' => '02', 'denominacion' => 'Cargo sintético', 'numero_cargos' => 3, 'asignacion_basica_mensual' => '1000001', 'origen_valor' => 'importado']);

        return $base;
    }

    private function createFrom(EscalaSalarialVigencia $base, array $extra = []): array
    {
        return ['anio' => $base->anio + 1, 'fuente' => 'Borrador sintético', 'vigencia_base_id' => $base->id, 'base_revision' => $base->revision] + $extra;
    }

    public function test_admin_lists_dynamic_years_and_searches_source_fields(): void
    {
        $this->base(2024);
        $this->base(2025);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data.vigencias')->assertJsonCount(1, 'data.filas')->assertJsonPath('data.filas.0.codigo', '099')->assertJsonPath('data.filas.0.grado', '02')->assertJsonPath('data.meta.total', 1);
        $this->getJson(self::URL.'?per_page=1')->assertOk()->assertJsonPath('data.meta.last_page', 1);
        foreach (['099', '02', 'sintético'] as $term) {
            $this->getJson(self::URL.'?q='.urlencode($term))->assertOk()->assertJsonCount(1, 'data.filas');
        }
        $this->getJson(self::URL.'?q=ausente')->assertOk()->assertJsonCount(0, 'data.filas');
    }

    public function test_secretary_and_employee_cannot_use_any_endpoint_even_with_permission(): void
    {
        $year = $this->base();
        $item = $year->items()->first();
        foreach (['secretario', 'funcionario'] as $role) {
            $user = $role === 'secretario' ? User::factory()->backoffice()->create(['estado' => true]) : User::factory()->create(['estado' => true]);
            $user->assignRole($role);
            $this->assertFalse($user->can('escala_salarial.gestionar'));
            $user->givePermissionTo('escala_salarial.gestionar');
            $this->actingAs($user, 'sanctum');
            foreach ([['get', ''], ['get', '/vigencias'], ['get', '/vigencias/'.$year->id], ['post', '/vigencias'], ['post', '/vigencias/previsualizar'], ['post', '/vigencias/'.$year->id.'/calcular'], ['post', '/vigencias/'.$year->id.'/publicar'], ['patch', '/vigencias/'.$year->id], ['patch', '/items/'.$item->id]] as [$method, $path]) {
                $this->{$method.'Json'}(self::URL.$path)->assertForbidden();
            }
        }
    }

    public function test_clone_without_percentage_preserves_structure_and_pending_values(): void
    {
        $base = $this->base();
        $response = $this->postJson(self::URL.'/vigencias', $this->createFrom($base))->assertCreated()->assertJsonPath('data.estado', 'borrador');
        $this->getJson(self::URL.'/vigencias/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.items.0.codigo', '099')->assertJsonPath('data.items.0.numero_cargos', 3)->assertJsonPath('data.items.0.asignacion_basica_mensual', null);
        $this->assertSame('1000001', $base->items()->first()->asignacion_basica_mensual);
        $this->postJson(self::URL.'/vigencias', $this->createFrom($base))->assertStatus(409)->assertJsonPath('code', 'SALARY_SCALE_YEAR_EXISTS');
    }

    public function test_create_without_base_uses_active_catalog_and_no_inferred_salary(): void
    {
        $cargo = Cargo::create(['codigo' => 'SST', 'grado' => '03', 'denominacion' => 'Cargo de prueba', 'nivel' => 'Profesional', 'estado' => true]);
        $response = $this->postJson(self::URL.'/vigencias', ['anio' => 2026, 'fuente' => 'Carga manual pendiente'])->assertCreated();
        $year = EscalaSalarialVigencia::findOrFail($response->json('data.id'));
        $this->assertTrue($year->items()->where('cargo_id', $cargo->id)->exists());
        $this->assertFalse($year->items()->whereNotNull('asignacion_basica_mensual')->exists());
    }

    public function test_server_preview_then_confirmed_generation_keeps_base_intact_and_audits(): void
    {
        $base = $this->base();
        $this->postJson(self::URL.'/vigencias/previsualizar', ['anio' => 2026, 'vigencia_base_id' => $base->id, 'porcentaje' => '7,00'])->assertOk()->assertJsonPath('data.cantidad', 1)->assertJsonPath('data.ejemplos.0.valor_nuevo', '1070001');
        $this->assertSame(1, EscalaSalarialVigencia::count());
        $data = $this->createFrom($base, ['porcentaje' => '7,00']);
        $this->postJson(self::URL.'/vigencias', $data)->assertUnprocessable();
        $response = $this->postJson(self::URL.'/vigencias', $data + ['confirmado' => true])->assertCreated()->assertJsonPath('data.porcentaje_referencia', '7.00');
        $this->getJson(self::URL.'/vigencias/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.items.0.asignacion_basica_mensual', '1070001')->assertJsonPath('data.items.0.origen_valor', 'calculado_por_porcentaje');
        $this->assertSame('1000001', $base->items()->first()->asignacion_basica_mensual);
        $audit = AuditLog::where('accion', 'salary_scale_percentage_applied')->firstOrFail();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame('7.00', $audit->metadata['porcentaje']);
    }

    public static function rounding(): array
    {
        return [['1', '50', '2'], ['101', '0.5', '102'], ['100', '0.49', '100'], ['100', '0,50', '101'], ['999999999999', '0', '999999999999']];
    }

    #[DataProvider('rounding')]
    public function test_exact_decimal_calculation_rounds_half_up(string $base, string $percent, string $expected): void
    {
        $this->assertSame($expected, SalaryScaleAmount::increase($base, $percent));
    }

    public function test_invalid_percentage_overflow_and_payload_cannot_create_partial_year(): void
    {
        $base = $this->base();
        foreach (['-1', 'NaN', 'Infinity', '1000', '7.123', '1e2'] as $value) {
            $this->postJson(self::URL.'/vigencias', $this->createFrom($base, ['porcentaje' => $value, 'confirmado' => true]))->assertUnprocessable();
        }
        foreach (['estado' => 'vigente', 'creado_por' => 1, 'manual_cargo_version_id' => 1, 'cargo_id' => 1] as $key => $value) {
            $this->postJson(self::URL.'/vigencias', $this->createFrom($base) + [$key => $value])->assertUnprocessable();
        }
        $base->items()->first()->update(['asignacion_basica_mensual' => '999999999999']);
        $this->postJson(self::URL.'/vigencias', $this->createFrom($base, ['porcentaje' => '100', 'confirmado' => true]))->assertUnprocessable();
        $this->assertSame(1, EscalaSalarialVigencia::count());
    }

    public function test_apply_to_empty_draft_only_once_and_stale_revision_is_rejected(): void
    {
        $base = $this->base();
        $year = $this->postJson(self::URL.'/vigencias', $this->createFrom($base))->json('data');
        $data = ['revision' => $year['revision'], 'vigencia_base_id' => $base->id, 'base_revision' => $base->revision, 'porcentaje' => '7', 'confirmado' => true];
        $updated = $this->postJson(self::URL.'/vigencias/'.$year['id'].'/calcular', $data)->assertOk()->json('data');
        $this->postJson(self::URL.'/vigencias/'.$year['id'].'/calcular', $data)->assertStatus(409)->assertJsonPath('code', 'SALARY_SCALE_CONFLICT');
        $data['revision'] = $updated['revision'];
        $this->postJson(self::URL.'/vigencias/'.$year['id'].'/calcular', $data)->assertStatus(409)->assertJsonPath('code', 'SALARY_SCALE_ALREADY_CALCULATED');
    }

    public function test_individual_edit_preserves_other_year_counts_and_rejects_concurrent_snapshot(): void
    {
        $base = $this->base();
        $id = $this->postJson(self::URL.'/vigencias', $this->createFrom($base))->json('data.id');
        $item = EscalaSalarialVigencia::find($id)->items()->first();
        $data = ['revision' => $item->revision, 'asignacion_basica_mensual' => '1234567', 'numero_cargos' => 9];
        $this->patchJson(self::URL.'/items/'.$item->id, $data)->assertOk()->assertJsonPath('data.origen_valor', 'manual');
        $this->patchJson(self::URL.'/items/'.$item->id, $data)->assertStatus(409);
        $this->assertSame(3, $base->items()->first()->numero_cargos);
        foreach (['0', '-2', '123.50', '123,50', '1000000000000'] as $value) {
            $this->patchJson(self::URL.'/items/'.$item->id, ['revision' => $item->fresh()->revision, 'asignacion_basica_mensual' => $value, 'numero_cargos' => 1])->assertUnprocessable();
        }
    }

    public function test_incomplete_publication_requires_completion_and_explicit_confirmation(): void
    {
        $year = $this->base();
        $year->items()->first()->update(['asignacion_basica_mensual' => null]);
        $this->postJson(self::URL.'/vigencias/'.$year->id.'/publicar', ['revision' => 1])->assertUnprocessable();
        $this->postJson(self::URL.'/vigencias/'.$year->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertUnprocessable();
        $this->assertSame('borrador', $year->fresh()->estado);
    }

    public function test_publish_archives_prior_year_protects_history_and_allows_later_act_metadata(): void
    {
        $old = $this->base(2024);
        $new = $this->base(2025);
        $this->postJson(self::URL.'/vigencias/'.$old->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertOk();
        $this->postJson(self::URL.'/vigencias/'.$new->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertOk()->assertJsonPath('data.estado', 'vigente')->assertJsonPath('data.acto_administrativo', null);
        $this->assertSame('archivada', $old->fresh()->estado);
        $this->assertSame('1000001', $old->items()->first()->asignacion_basica_mensual);
        $this->postJson(self::URL.'/vigencias/'.$new->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertStatus(409);
        $item = $new->items()->first();
        $this->patchJson(self::URL.'/items/'.$item->id, ['revision' => $item->revision, 'asignacion_basica_mensual' => '2', 'numero_cargos' => 1])->assertStatus(409)->assertJsonPath('code', 'SALARY_SCALE_READ_ONLY');
        $this->patchJson(self::URL.'/vigencias/'.$new->id, ['revision' => $new->fresh()->revision, 'fuente' => 'Fuente sintética', 'acto_administrativo' => 'Acto de prueba'])->assertOk()->assertJsonPath('data.acto_administrativo', 'Acto de prueba');
        $this->assertSame('1000001', $item->fresh()->asignacion_basica_mensual);
    }

    public function test_database_guard_rejects_direct_changes_of_published_item(): void
    {
        $year = $this->base();
        $year->update(['estado' => 'vigente']);
        try {
            DB::transaction(fn () => $year->items()->update(['asignacion_basica_mensual' => 7]));
            $this->fail('Historical update accepted');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('SALARY_SCALE_READ_ONLY', $exception->getMessage());
        }
        $this->assertSame('1000001', $year->items()->first()->asignacion_basica_mensual);
    }

    private function csv(string $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'salary-test-');
        file_put_contents($file, implode(',', SalaryScaleImportService::HEADERS)."\n".$rows);

        return $file;
    }

    public function test_import_dry_run_no_actor_no_writes_and_mapping_preserves_source_zeroes(): void
    {
        Cargo::create(['codigo' => '099', 'grado' => '02', 'denominacion' => 'Exacto', 'nivel' => 'Profesional', 'estado' => true]);
        $file = $this->csv("2024,099,02,Exacto,2,1000001\n2025,099,02,Otra denominación,4,1070001\n");
        try {
            $this->artisan('salary-scale:import', ['--file' => $file, '--dry-run' => true])->assertSuccessful();
            $this->assertSame(0, EscalaSalarialVigencia::count());
            $this->artisan('salary-scale:import', ['--file' => $file])->assertFailed();
            $this->artisan('salary-scale:import', ['--file' => $file, '--actor-id' => (string) $this->admin->id, '--fuente' => 'Fuente sintética'])->assertSuccessful();
            $items = EscalaSalarialVigencia::with('items')->orderBy('anio')->get();
            $this->assertNotNull($items[0]->items[0]->cargo_id);
            $this->assertNull($items[1]->items[0]->cargo_id);
            $this->assertSame('02', $items[1]->items[0]->grado);
            $this->assertSame(4, $items[1]->items[0]->numero_cargos);
            $before = AuditLog::count();
            $this->artisan('salary-scale:import', ['--file' => $file, '--actor-id' => (string) $this->admin->id, '--fuente' => 'Fuente sintética'])->assertSuccessful();
            $this->assertSame($before, AuditLog::count());
        } finally {
            unlink($file);
        }
    }

    public function test_import_structural_errors_and_duplicates_write_nothing(): void
    {
        foreach (["2024,099,02,Uno,2,1000001\n2024,099,02,Uno,2,1000001\n", "2024,099,02,Uno,2,1000001\n2025,099,03,Dos,2,$ 1.000\n", ''] as $rows) {
            $file = $this->csv($rows);
            try {
                $this->artisan('salary-scale:import', ['--file' => $file, '--actor-id' => (string) $this->admin->id])->assertFailed();
            } finally {
                unlink($file);
            }
            $this->assertSame(0, EscalaSalarialVigencia::count());
        }
    }

    private function reviewedCatalog(): array
    {
        $targets = [
            ['290', '10', 'Comandante de Transito'],
            ['233', '09', 'Inspector de Policía Urbano Primera Categoría'],
            ['438', '12', 'Secretario Ejecutivo del Despacho del Alcalde'],
            ['202', '09', 'Comisario de Familia'],
            ['312', '08', 'Inspector de Tránsito y Transporte –'],
        ];
        $cargos = [];
        foreach ($targets as [$code, $grade, $name]) {
            $cargos[$code] = Cargo::updateOrCreate(['codigo' => $code, 'grado' => $grade], ['denominacion' => $name, 'nivel' => 'Profesional', 'estado' => true]);
        }

        return $cargos;
    }

    public function test_reviewed_mappings_import_source_values_and_preserve_pending_rows_without_fk(): void
    {
        $cargos = $this->reviewedCatalog();
        $stream = fopen(database_path('data/salary-scale/salary-scale-2024-2025.csv'), 'rb');
        fgetcsv($stream, 0, ',', '"', '');
        $source = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if (in_array($row[1] ?? '', ['290', '233', '438', '202', '312'], true)) {
                $source[] = $row;
            }
        }
        fclose($stream);
        $this->assertCount(10, $source);
        $file = $this->csv(implode("\n", array_map(fn ($row) => implode(',', $row), $source))."\n");
        try {
            $summary = app(SalaryScaleImportService::class)->inspect($file);
            $this->assertSame(10, $summary['validas']);
            $this->assertSame(6, $summary['con_cargo_relacionado']);
            $this->assertSame(4, $summary['sin_cargo_relacionado']);
            $this->assertSame(0, $summary['ambiguas']);
            $this->assertCount(6, $summary['mappings_explicitos']);
            $this->assertSame([], $summary['errores']);
            $this->artisan('salary-scale:import', ['--file' => $file, '--actor-id' => (string) $this->admin->id, '--fuente' => 'Fuente revisada de prueba'])->assertSuccessful();
            foreach ($source as [$number, $code, $grade, $name, $count, $value]) {
                $year = EscalaSalarialVigencia::where('anio', $number)->firstOrFail();
                $item = $year->items()->where('codigo', $code)->where('grado', $grade)->where('denominacion', $name)->sole();
                $this->assertSame($grade, $item->grado);
                $this->assertSame($name, $item->denominacion);
                $this->assertSame((int) $count, $item->numero_cargos);
                $this->assertSame($value, $item->asignacion_basica_mensual);
                $this->assertSame(in_array($code, ['202', '312'], true) ? null : $cargos[$code]->id, $item->cargo_id);
                $this->assertSame('borrador', $year->estado);
                $this->assertNull($year->acto_administrativo);
                $this->assertNull($year->fecha_vigencia);
            }
            $this->assertFalse(EscalaSalarialVigencia::where('anio', 2026)->exists());
            foreach (AuditLog::where('accion', 'salary_scale_year_imported')->get() as $audit) {
                $this->assertCount(3, $audit->metadata['mappings_explicitos']);
                $this->assertSame(2, $audit->metadata['sin_cargo_relacionado']);
            }
            $this->getJson(self::URL.'?q=Comisario')->assertOk()->assertJsonPath('data.filas.0.grado', '13')->assertJsonPath('data.filas.0.valores.0.cargo_id', null)->assertJsonPath('data.filas.0.valores.0.asignacion_basica_mensual', '11284768');
            $this->getJson(self::URL.'?q=Inspector de Tránsito')->assertOk()->assertJsonPath('data.filas.0.denominacion', 'Inspector de Tránsito')->assertJsonPath('data.filas.0.valores.0.cargo_id', null);
            $before = AuditLog::count();
            $this->artisan('salary-scale:import', ['--file' => $file, '--actor-id' => (string) $this->admin->id, '--fuente' => 'Fuente revisada de prueba'])->assertSuccessful();
            $this->assertSame($before, AuditLog::count());
        } finally {
            unlink($file);
        }
    }

    public function test_mapping_never_normalizes_other_names_codes_grades_or_years(): void
    {
        $this->reviewedCatalog();
        $file = $this->csv("2024,290,10,Comandante de tránsito,2,6856521\n2024,290,10,Comandante de Tránsito ,2,6856521\n2024,290,11,Comandante de Tránsito,2,6856521\n2024,291,10,Comandante de Tránsito,2,6856521\n2023,290,10,Comandante de Tránsito,2,6856521\n");
        try {
            $summary = app(SalaryScaleImportService::class)->inspect($file);
            $this->assertSame(5, $summary['validas']);
            $this->assertSame(0, $summary['con_cargo_relacionado']);
            $this->assertSame(5, $summary['sin_cargo_relacionado']);
            $this->assertSame([], $summary['mappings_explicitos']);
            $this->assertSame([], $summary['errores']);
        } finally {
            unlink($file);
        }
    }

    public function test_mapping_requires_its_exact_catalog_target_and_does_not_use_a_fixed_id(): void
    {
        $cargos = $this->reviewedCatalog();
        $cargos['290']->update(['denominacion' => 'Denominación distinta']);
        $file = $this->csv("2024,290,10,Comandante de Tránsito,2,6856521\n");
        try {
            $summary = app(SalaryScaleImportService::class)->inspect($file);
            $this->assertSame(1, $summary['sin_cargo_relacionado']);
            $this->assertNull($summary['filas'][0]['cargo_id']);
            $this->assertSame([], $summary['mappings_explicitos']);
        } finally {
            unlink($file);
        }
    }

    public function test_import_refuses_a_summary_flagged_as_ambiguous(): void
    {
        $file = $this->csv("2024,099,02,Sin relación,2,1000001\n");
        try {
            $summary = app(SalaryScaleImportService::class)->inspect($file);
            $summary['ambiguas'] = 1;
            try {
                app(SalaryScaleImportService::class)->import($summary, $this->admin->id, 'Fuente sintética');
                $this->fail('Ambiguous import accepted');
            } catch (ValidationException) {
                $this->assertSame(0, EscalaSalarialVigencia::count());
            }
        } finally {
            unlink($file);
        }
    }

    public function test_import_conflict_rolls_back_all_years_and_cannot_overwrite(): void
    {
        $this->base(2025);
        $file = $this->csv("2024,099,02,Uno,2,1000001\n2025,099,02,Cargo sintético,3,99\n");
        try {
            $summary = app(SalaryScaleImportService::class)->inspect($file);
            try {
                app(SalaryScaleImportService::class)->import($summary, $this->admin->id, 'Fuente sintética');
                $this->fail('Overwrite accepted');
            } catch (ValidationException) {
                $this->assertSame(1, EscalaSalarialVigencia::count());
            }
        } finally {
            unlink($file);
        }
    }
}
